<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Archivo .ai en el formato clásico de Illustrator (PostScript, ver "Adobe
 * Illustrator File Format Specification", 1998), que Illustrator abre con
 * capas reales:
 *   - "Arte": la hoja que dibujó dompdf, rasterizada con Ghostscript a CMYK
 *     (ILLUSTRATOR_EXPORT_DPI, 300 por defecto). No es editable.
 *   - "Lineas de corte" (arriba): los trazos de CutLineGeometry, vectoriales,
 *     en el color directo CutContour.
 *
 * Illustrator no lee capas de un PDF ajeno ni de un SVG; este formato sí
 * las declara (operadores Lb/Ln/LB). Un diseño con varias hojas sale en un
 * solo .ai con las hojas una al lado de la otra (el formato clásico tiene
 * una sola mesa de trabajo).
 */
class IllustratorExport
{
    private const CM_TO_PT = 72 / 2.54;
    private const GAP_PT = 2 * self::CM_TO_PT; // separación entre hojas
    private const LAYER_ART = 'Arte';
    // Sin tilde a propósito: el nombre va en un string PostScript de 1 byte
    // y la codificación depende de la plataforma (Mac/Windows).
    private const LAYER_CUT = 'Lineas de corte';
    private const HEX_BYTES_PER_LINE = 60;

    /**
     * $tmpFiles/$grupos: los PDF temporales de cada grupo de tamaño y sus
     * páginas resueltas, en el mismo orden (ver
     * EtiquetaService::generarEtiquetasDesdePaginas). Escribe $outPath.
     */
    public static function export(array $tmpFiles, array $grupos, string $outPath, string $title): void
    {
        $workDir = $outPath . '.work';
        @mkdir($workDir, 0755, true);

        try {
            $hojas = [];
            foreach ($tmpFiles as $grupoIdx => $tmpFile) {
                $pams = self::rasterizar($tmpFile, "{$workDir}/g{$grupoIdx}");
                foreach ($grupos[$grupoIdx]['pages'] as $i => $page) {
                    if (!isset($pams[$i])) {
                        throw new \RuntimeException("Ghostscript no generó la página " . ($i + 1));
                    }
                    $hojas[] = ['page' => $page, 'pam' => $pams[$i]];
                }
            }

            self::escribirAi($hojas, $outPath, $title);
        } finally {
            foreach (glob("{$workDir}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($workDir);
        }
    }

    /** PDF → un PAM CMYK de 8 bits por página. */
    private static function rasterizar(string $pdfPath, string $prefix): array
    {
        $bin = config('services.illustrator.ghostscript_bin');
        $dpi = max(72, (int) config('services.illustrator.dpi', 300));

        $result = Process::timeout(300)->run([
            $bin, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
            '-sDEVICE=pamcmyk32', "-r{$dpi}",
            '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
            "-sOutputFile={$prefix}-%03d.pam", $pdfPath,
        ]);

        if (!$result->successful()) {
            $detalle = trim($result->errorOutput() ?: $result->output());
            throw new \RuntimeException(
                "No se pudo rasterizar el PDF con Ghostscript ({$bin}). ¿Está instalado? " . mb_substr($detalle, 0, 300)
            );
        }

        $pams = glob("{$prefix}-*.pam") ?: [];
        sort($pams);
        return $pams;
    }

    private static function escribirAi(array $hojas, string $outPath, string $title): void
    {
        // Las hojas van una al lado de la otra, alineadas abajo.
        $x = 0.0;
        $artH = 0.0;
        foreach ($hojas as $i => $hoja) {
            $wPt = (float) ($hoja['page']['sheet']['width_cm'] ?? 18.5) * self::CM_TO_PT;
            $hPt = (float) ($hoja['page']['sheet']['height_cm'] ?? 29) * self::CM_TO_PT;
            $hojas[$i] += ['x' => $x, 'w' => $wPt, 'h' => $hPt];
            $x += $wPt + self::GAP_PT;
            $artH = max($artH, $hPt);
        }
        $artW = $x - self::GAP_PT;

        foreach ($hojas as $i => $hoja) {
            $config = CutLineGeometry::configPagina($hoja['page']);
            $hojas[$i]['cut'] = $config;
            $hojas[$i]['trazados'] = $config ? CutLineGeometry::trazadosPagina($hoja['page']) : [];
        }
        $spot = self::colorCorte($hojas);

        $out = fopen($outPath, 'wb');
        if (!$out) {
            throw new \RuntimeException("No se pudo escribir {$outPath}");
        }

        try {
            self::escribirEncabezado($out, $artW, $artH, $title, $spot);

            // Capa "Arte": una imagen por hoja.
            fwrite($out, "%AI5_BeginLayer\n1 1 1 1 0 0 0 79 128 255 Lb\n(" . self::LAYER_ART . ") Ln\n0 A\n");
            foreach ($hojas as $hoja) {
                self::escribirImagen($out, $hoja['pam'], $hoja['x'], $hoja['w'], $hoja['h']);
            }
            fwrite($out, "LB\n%AI5_EndLayer--\n");

            // Capa "Lineas de corte", arriba (se escribe después).
            fwrite($out, "%AI5_BeginLayer\n1 1 1 1 0 0 1 255 79 79 Lb\n(" . self::LAYER_CUT . ") Ln\n0 A\n");
            foreach ($hojas as $hoja) {
                if (!$hoja['cut'] || !$hoja['trazados']) {
                    continue;
                }
                $c = self::cmykDeColor($hoja['cut']['color']);
                $w = $hoja['cut']['width_cm'] * self::CM_TO_PT;
                fwrite($out, sprintf(
                    "0 O 1 R\n%s (CutContour) 0 X\n0 J 0 j %s w 4 M []0 d\n0 XR\n",
                    self::nums($c), self::num($w)
                ));
                foreach ($hoja['trazados'] as $trazado) {
                    fwrite($out, self::trazadoAi($trazado, $hoja['x'], $hoja['h']));
                }
            }
            fwrite($out, "LB\n%AI5_EndLayer--\n");

            fwrite($out, "%%PageTrailer\ngsave annotatepage grestore showpage\n%%Trailer\n%%EOF\n");
        } finally {
            fclose($out);
        }
    }

    private static function escribirEncabezado($out, float $w, float $h, string $title, array $spot): void
    {
        $title = self::psString(preg_replace('/[^\x20-\x7E]/', '_', $title));
        $wi = (int) ceil($w);
        $hi = (int) ceil($h);
        $cx = self::num($w / 2);
        $cy = self::num($h / 2);

        $lines = [
            '%!PS-Adobe-3.0',
            '%%Creator: Adobe Illustrator(R) 7.0',
            '%%For: (Etiquecosas) ()',
            "%%Title: {$title}",
            '%%CreationDate: (' . date('n/j/Y') . ') (' . date('g:i A') . ')',
            "%%BoundingBox: 0 0 {$wi} {$hi}",
            '%%HiResBoundingBox: 0 0 ' . self::num($w) . ' ' . self::num($h),
            '%%DocumentProcessColors: Cyan Magenta Yellow Black',
            '%%DocumentCustomColors: (CutContour)',
            '%%CMYKCustomColor: ' . self::nums($spot) . ' (CutContour)',
            '%%DocumentNeededResources: procset Adobe_level2_AI5 1.2 0',
            '%%+ procset Adobe_ColorImage_AI6 1.1 0',
            '%%+ procset Adobe_Illustrator_AI5 1.2 0',
            '%AI5_FileFormat 3',
            '%AI3_ColorUsage: Color',
            '%AI7_ImageSettings: 1',
            // Caja de plantilla degenerada en el centro: deja el origen de
            // las reglas abajo a la izquierda de la mesa de trabajo.
            "%AI3_TemplateBox: {$cx} {$cy} {$cx} {$cy}",
            "%AI3_TileBox: 0 0 {$wi} {$hi}",
            '%AI3_DocumentPreview: None',
            '%AI5_ArtSize: ' . self::num($w) . ' ' . self::num($h),
            '%AI5_RulerUnits: 4',
            '%AI5_ArtFlags: 0 0 0 1 0 0 1 0 0',
            '%AI5_TargetResolution: 800',
            '%AI5_NumLayers: 2',
            '%%EndComments',
            '%%BeginProlog',
            '%%EndProlog',
            '%%BeginSetup',
            '%%EndSetup',
        ];
        fwrite($out, implode("\n", $lines) . "\n");
    }

    /**
     * Imagen CMYK (operador XI, datos en hexadecimal en líneas que empiezan
     * con %, como en los ejemplos de la especificación). La matriz lleva
     * píxeles a puntos con origen abajo a la izquierda y las filas van de
     * arriba hacia abajo, como en el operador image de PostScript.
     */
    private static function escribirImagen($out, string $pamPath, float $x, float $wPt, float $hPt): void
    {
        $pam = fopen($pamPath, 'rb');
        $header = self::leerHeaderPam($pam, $pamPath);
        $pw = $header['width'];
        $ph = $header['height'];

        $sx = $wPt / $pw;
        $sy = $hPt / $ph;
        $matrix = config('services.illustrator.raster_invert')
            ? [$sx, 0, 0, -$sy, $x, $hPt]
            : [$sx, 0, 0, $sy, $x, 0];

        fwrite($out, "%AI5_File:\n%AI5_BeginRaster\n");
        fwrite($out, sprintf(
            "[ %s ] 0 0 %d %d %d %d 8 4 0 0 0 0 XI\n",
            self::nums($matrix), $pw, $ph, $pw, $ph
        ));

        $restante = $pw * $ph * 4;
        $chunk = self::HEX_BYTES_PER_LINE * 1024;
        while ($restante > 0) {
            $data = fread($pam, min($chunk, $restante));
            if ($data === false || $data === '') {
                break;
            }
            $restante -= strlen($data);
            $lineas = str_split(strtoupper(bin2hex($data)), self::HEX_BYTES_PER_LINE * 2);
            fwrite($out, '%' . implode("\n%", $lineas) . "\n");
        }
        fclose($pam);

        if ($restante > 0) {
            throw new \RuntimeException("Imagen incompleta: {$pamPath}");
        }
        fwrite($out, "%AI5_EndRaster\n");
    }

    private static function leerHeaderPam($fh, string $path): array
    {
        if (trim((string) fgets($fh)) !== 'P7') {
            throw new \RuntimeException("PAM inválido: {$path}");
        }
        $h = [];
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === 'ENDHDR') {
                break;
            }
            [$k, $v] = array_pad(explode(' ', $line, 2), 2, '');
            $h[$k] = $v;
        }
        if (($h['DEPTH'] ?? '') !== '4' || ($h['MAXVAL'] ?? '') !== '255') {
            throw new \RuntimeException("PAM no es CMYK de 8 bits: {$path}");
        }
        return ['width' => (int) $h['WIDTH'], 'height' => (int) $h['HEIGHT']];
    }

    /** Segmentos en cm (Y hacia abajo) → path AI en pt (Y hacia arriba). */
    private static function trazadoAi(array $trazado, float $offsetX, float $hPt): string
    {
        $pt = fn($x, $y) => self::num($offsetX + $x * self::CM_TO_PT) . ' ' . self::num($hPt - $y * self::CM_TO_PT);
        $ops = [];
        $cerrado = false;
        foreach ($trazado as $seg) {
            switch ($seg[0]) {
                case 'M':
                    $ops[] = $pt($seg[1], $seg[2]) . ' m';
                    break;
                case 'L':
                    $ops[] = $pt($seg[1], $seg[2]) . ' L';
                    break;
                case 'C':
                    $ops[] = $pt($seg[1], $seg[2]) . ' ' . $pt($seg[3], $seg[4]) . ' ' . $pt($seg[5], $seg[6]) . ' C';
                    break;
                case 'Z':
                    $cerrado = true;
                    break;
            }
        }
        // s = cerrar y trazar; S = trazar abierto.
        return implode("\n", $ops) . "\n" . ($cerrado ? 's' : 'S') . "\n";
    }

    /** CMYK del color directo: el de la primera hoja con líneas. */
    private static function colorCorte(array $hojas): array
    {
        foreach ($hojas as $hoja) {
            if ($hoja['cut']) {
                return self::cmykDeColor($hoja['cut']['color']);
            }
        }
        return [0, 1, 0, 0];
    }

    private static function cmykDeColor(array $color): array
    {
        if ($color['space'] === 'cmyk') {
            return $color['values'];
        }
        [$r, $g, $b] = $color['values'];
        $k = 1 - max($r, $g, $b);
        if ($k >= 1) {
            return [0, 0, 0, 1];
        }
        return [(1 - $r - $k) / (1 - $k), (1 - $g - $k) / (1 - $k), (1 - $b - $k) / (1 - $k), $k];
    }

    private static function psString(string $s): string
    {
        return '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s) . ')';
    }

    private static function num(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }

    private static function nums(array $vals): string
    {
        return implode(' ', array_map(fn($v) => self::num((float) $v), $vals));
    }
}
