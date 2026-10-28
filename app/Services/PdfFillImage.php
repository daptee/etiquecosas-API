<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Imagen de fondo (`fill.type = image`) de una etiqueta con forma `custom`:
 * dompdf no puede recortar un <img> con un path arbitrario (border-radius
 * solo sirve para rectángulos/círculos, y el clipPath de php-svg-lib no
 * recorta), así que se arma un PNG ya recortado: la imagen estirada a la
 * caja del elemento y transparente fuera del contorno (outline_svg).
 *
 * El PNG queda cacheado en disco por imagen + contorno + tamaño: la misma
 * etiqueta se repite muchas veces en una hoja y entre pedidos.
 */
class PdfFillImage
{
    private const DPI = 300;
    private const MAX_SIDE_PX = 2400;
    // Supersampling de la máscara para que el borde no quede serruchado.
    private const MASK_SCALE = 4;

    /**
     * Path absoluto del PNG recortado, o null si no se puede (contorno con
     * curvas/ilegible, imagen que GD no lee —ej. SVG—); en ese caso el
     * caller dibuja la imagen sin recortar.
     */
    public static function recortarConContorno(string $imagePath, array $el): ?string
    {
        $widthCm = (float) ($el['width_cm'] ?? 0);
        $heightCm = (float) ($el['height_cm'] ?? 0);
        if ($widthCm <= 0 || $heightCm <= 0 || !is_file($imagePath)) {
            return null;
        }

        $scale = min(self::DPI / 2.54, self::MAX_SIDE_PX / max($widthCm, $heightCm));
        $w = max(1, (int) round($widthCm * $scale));
        $h = max(1, (int) round($heightCm * $scale));

        $cacheDir = storage_path('app/pdf-fill-cache');
        $cacheKey = md5(implode('|', [
            $imagePath,
            filemtime($imagePath),
            $el['resolved_shape_outline_svg'] ?? '',
            $el['resolved_shape_width_cm'] ?? '',
            $el['resolved_shape_height_cm'] ?? '',
            $w,
            $h,
        ]));
        $cachePath = $cacheDir . '/' . $cacheKey . '.png';
        if (is_file($cachePath)) {
            return $cachePath;
        }

        $poligonos = self::poligonos($el, $w * self::MASK_SCALE, $h * self::MASK_SCALE);
        if (!$poligonos) {
            Log::warning('[PDF-FILL] contorno custom ilegible, la imagen va sin recortar', ['el_id' => $el['id'] ?? null]);
            return null;
        }

        $src = @imagecreatefromstring((string) file_get_contents($imagePath));
        if (!$src) {
            Log::warning('[PDF-FILL] GD no pudo leer la imagen, va sin recortar', ['el_id' => $el['id'] ?? null, 'image' => $imagePath]);
            return null;
        }

        // Imagen estirada a la caja (100% × 100%, sin mantener proporción).
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);

        $mask = self::mascara($poligonos, $w, $h);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $cobertura = imagecolorat($mask, $x, $y) & 0xFF;
                if ($cobertura === 255) {
                    continue;
                }
                $rgba = imagecolorat($dst, $x, $y);
                $opacidad = 127 - (($rgba >> 24) & 0x7F);
                $alpha = 127 - (int) round($opacidad * $cobertura / 255);
                imagesetpixel($dst, $x, $y, ($alpha << 24) | ($rgba & 0xFFFFFF));
            }
        }
        imagedestroy($mask);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        // Escritura atómica: dos PDFs en paralelo no leen un PNG a medias.
        $tmpPath = $cachePath . '.' . uniqid() . '.tmp';
        imagepng($dst, $tmpPath);
        imagedestroy($dst);
        rename($tmpPath, $cachePath);

        return $cachePath;
    }

    /**
     * Subtrazados del outline_svg escalados a una caja de $w × $h px, como
     * listas planas [x1, y1, x2, y2, ...] para imagefilledpolygon.
     */
    private static function poligonos(array $el, int $w, int $h): array
    {
        $trazado = CutLineGeometry::trazadoCustom($el, 0, 0, $w, $h);
        if (!$trazado) {
            return [];
        }

        $poligonos = [];
        $actual = [];
        foreach ($trazado as $seg) {
            if ($seg[0] === 'M' && $actual) {
                $poligonos[] = $actual;
                $actual = [];
            }
            if ($seg[0] === 'M' || $seg[0] === 'L') {
                $actual[] = (int) round($seg[1]);
                $actual[] = (int) round($seg[2]);
            }
        }
        if ($actual) {
            $poligonos[] = $actual;
        }

        return array_values(array_filter($poligonos, fn($p) => count($p) >= 6));
    }

    /**
     * Máscara en escala de grises de $w × $h (255 = adentro del contorno),
     * dibujada a MASK_SCALE× y achicada para tener bordes suavizados.
     */
    private static function mascara(array $poligonos, int $w, int $h): \GdImage
    {
        $big = imagecreatetruecolor($w * self::MASK_SCALE, $h * self::MASK_SCALE);
        $blanco = imagecolorallocate($big, 255, 255, 255);
        foreach ($poligonos as $puntos) {
            imagefilledpolygon($big, $puntos, $blanco);
        }

        $mask = imagecreatetruecolor($w, $h);
        imagecopyresampled($mask, $big, 0, 0, 0, 0, $w, $h, imagesx($big), imagesy($big));
        imagedestroy($big);

        return $mask;
    }
}
