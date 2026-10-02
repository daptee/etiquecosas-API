<?php

namespace App\Services;

/**
 * Arma un `product_pdf_designs.data` (pages[].elements[]) a partir de un PDF
 * YA GENERADO por este mismo backend (vistas legacy de tematica/*) — no es un
 * parser de PDF genérico, ver PdfContentStreamParser. Los íconos (sean una
 * imagen `Do` o un SVG convertido a trazos vectoriales sueltos) se detectan
 * y se agregan con icon_id=1 (placeholder fijo) y todo texto queda con
 * font_id null — se completan a mano en el editor después.
 * Ver PDF_IMPORTAR_DESDE_PDF.md.
 */
class PdfDesignImportService
{
    private const PT_PER_CM = 28.3465;

    public static function import(string $pdfPath): array
    {
        $raw = file_get_contents($pdfPath);
        $pageSizePt = self::extractFirstMediaBox($raw) ?? ['w' => 18.5 * self::PT_PER_CM, 'h' => 29 * self::PT_PER_CM];

        $content = self::extractConcatenatedContent($raw);
        $primitives = PdfContentStreamParser::parse($content);

        [$rects, $images, $texts] = self::splitByType($primitives);

        // Una hoja de etiquetas casi siempre tiene un rect que cubre (casi)
        // toda la página de fondo — eso es el color de la HOJA, no un
        // elemento de etiqueta puntual.
        $pageArea = $pageSizePt['w'] * $pageSizePt['h'];
        $sheetBackgroundColor = null;
        $cellRects = [];
        foreach ($rects as $rect) {
            $area = $rect['w'] * $rect['h'];
            if ($rect['filled'] && $area >= $pageArea * 0.8 && $sheetBackgroundColor === null) {
                $sheetBackgroundColor = $rect['color'];
                continue;
            }
            $cellRects[] = $rect;
        }

        // Los íconos de estos PDFs legacy casi siempre son un SVG convertido
        // a docenas de trazos vectoriales sueltos (no una imagen `Do`), así
        // que acá aparecen como un montón de rects chiquitos anidados dentro
        // del rect real de la celda. Nos quedamos solo con el rect "de
        // afuera" de cada posición (el fondo real) y juntamos todo lo que
        // queda anidado adentro como el área del ícono de esa celda.
        [$cellRects, $iconFragmentsByCell] = self::splitOuterRectsAndFragments($cellRects);

        // Para cada texto/imagen, la celda más chica que lo contiene (la más
        // "específica" — evita que todo termine agrupado en el rect más
        // grande si hay varios anidados).
        $textsByCell = [];
        $looseTexts = [];
        foreach ($texts as $t) {
            $cellIdx = self::findSmallestContainingRect($cellRects, $t['x'], $t['y']);
            if ($cellIdx === null) {
                $looseTexts[] = $t;
            } else {
                $textsByCell[$cellIdx][] = $t;
            }
        }

        $imagesByCell = [];
        foreach ($images as $img) {
            $cx = $img['x'] + $img['w'] / 2;
            $cy = $img['y'] + $img['h'] / 2;
            $cellIdx = self::findSmallestContainingRect($cellRects, $cx, $cy);
            if ($cellIdx !== null) {
                $imagesByCell[$cellIdx][] = $img;
            }
        }

        $elements = [];
        $pageHeightPt = $pageSizePt['h'];

        foreach ($cellRects as $idx => $rect) {
            $elements[] = self::rectToBackgroundElement($rect, $pageHeightPt);

            // El ícono real (imagen `Do`) tiene prioridad sobre los trazos
            // vectoriales sueltos — si hay ambos en la misma celda, es raro,
            // pero la imagen da una caja más confiable.
            $iconBoxes = !empty($imagesByCell[$idx]) ? $imagesByCell[$idx] : ($iconFragmentsByCell[$idx] ?? []);
            if (!empty($iconBoxes)) {
                $elements[] = self::iconBoxToElement($iconBoxes, $pageHeightPt);
            }

            if (!empty($textsByCell[$idx])) {
                $elements[] = self::textsToTextElement($textsByCell[$idx], $pageHeightPt, $rect);
            }
        }

        // Texto suelto (ej. "PEDIDO # 12345" al costado) — no se pierde,
        // queda como elemento top-level sin celda.
        foreach (self::mergeLooseTextLines($looseTexts) as $group) {
            $elements[] = self::textsToTextElement($group, $pageHeightPt, null);
        }

        $sheet = [
            'width_cm' => round($pageSizePt['w'] / self::PT_PER_CM, 2),
            'height_cm' => round($pageSizePt['h'] / self::PT_PER_CM, 2),
        ];
        if ($sheetBackgroundColor) {
            $sheet['background_color'] = ['mode' => 'hex', 'value' => $sheetBackgroundColor];
        }

        return [
            'pages' => [[
                'id' => 'page-imported-' . uniqid(),
                'name' => 'Página importada',
                'sheet' => $sheet,
                'elements' => $elements,
            ]],
        ];
    }

    private static function splitByType(array $primitives): array
    {
        $rects = [];
        $images = [];
        $texts = [];
        foreach ($primitives as $p) {
            match ($p['type']) {
                'rect' => $rects[] = $p,
                'image' => $images[] = $p,
                'text' => $texts[] = $p,
                default => null,
            };
        }
        return [$rects, $images, $texts];
    }

    /**
     * Índice (en $rects) del rectángulo de MENOR área que contiene el punto
     * (x,y) — null si ninguno lo contiene. $rects ya viene sin el fondo de
     * toda la hoja (ver import()).
     */
    private static function findSmallestContainingRect(array $rects, float $x, float $y): ?int
    {
        $bestIdx = null;
        $bestArea = INF;
        foreach ($rects as $idx => $rect) {
            $inside = $x >= $rect['x'] - 1 && $x <= $rect['x'] + $rect['w'] + 1
                && $y >= $rect['y'] - 1 && $y <= $rect['y'] + $rect['h'] + 1;
            if (!$inside) {
                continue;
            }
            $area = $rect['w'] * $rect['h'];
            if ($area < $bestArea) {
                $bestArea = $area;
                $bestIdx = $idx;
            }
        }
        return $bestIdx;
    }

    /**
     * Separa los rects en "de afuera" (uno por celda — el fondo real) y
     * "fragmentos" (cualquier rect contenido dentro de otro: un ícono SVG
     * convertido a trazos vectoriales sueltos, una franja decorativa interna,
     * un duplicado exacto por relleno+trazo del mismo path, etc.). Devuelve
     * `[$outerRects, $fragmentsByOuterIndex]`, con el índice de
     * $fragmentsByOuterIndex ya alineado al índice final de $outerRects.
     */
    private static function splitOuterRectsAndFragments(array $rects): array
    {
        $sorted = $rects;
        usort($sorted, fn($a, $b) => ($b['w'] * $b['h']) <=> ($a['w'] * $a['h']));

        $outer = [];
        $fragments = []; // array<int outerKey, array rect[]>, outerKey = object id temporal
        foreach ($sorted as $rect) {
            $parentKey = null;
            foreach ($outer as $key => $o) {
                if (self::rectContains($o, $rect)) {
                    $parentKey = $key;
                    break;
                }
            }
            if ($parentKey === null) {
                $outer[] = $rect;
            } else {
                $fragments[$parentKey][] = $rect;
            }
        }

        // Orden de lectura natural (arriba-abajo, izquierda-derecha) en vez
        // del orden por área que se usó para filtrar — hay que reordenar
        // $fragments junto con $outer para que los índices sigan alineados.
        $order = array_keys($outer);
        usort($order, function ($a, $b) use ($outer) {
            $cmp = $outer[$b]['y'] <=> $outer[$a]['y'];
            return $cmp !== 0 ? $cmp : $outer[$a]['x'] <=> $outer[$b]['x'];
        });

        $outerSorted = [];
        $fragmentsSorted = [];
        foreach ($order as $newIdx => $oldKey) {
            $outerSorted[$newIdx] = $outer[$oldKey];
            if (!empty($fragments[$oldKey])) {
                $fragmentsSorted[$newIdx] = $fragments[$oldKey];
            }
        }

        return [$outerSorted, $fragmentsSorted];
    }

    private static function rectContains(array $outer, array $inner): bool
    {
        $tol = 1.0; // pt
        return $inner['x'] >= $outer['x'] - $tol
            && $inner['x'] + $inner['w'] <= $outer['x'] + $outer['w'] + $tol
            && $inner['y'] >= $outer['y'] - $tol
            && $inner['y'] + $inner['h'] <= $outer['y'] + $outer['h'] + $tol;
    }

    private static function rectToBackgroundElement(array $rect, float $pageHeightPt): array
    {
        $el = [
            'id' => 'el-imported-' . uniqid(),
            'type' => 'background',
            'x_cm' => round($rect['x'] / self::PT_PER_CM, 2),
            'y_cm' => round(($pageHeightPt - $rect['y'] - $rect['h']) / self::PT_PER_CM, 2),
            'width_cm' => round($rect['w'] / self::PT_PER_CM, 2),
            'height_cm' => round($rect['h'] / self::PT_PER_CM, 2),
            'z_index' => 0,
            'color' => ['mode' => 'hex', 'value' => $rect['color']],
            'editable_by_customer' => false,
        ];

        if (!empty($rect['borderColor']) && ($rect['borderWidthPt'] ?? 0) > 0) {
            $el['border'] = [
                'width_cm' => round($rect['borderWidthPt'] / self::PT_PER_CM, 3),
                'color' => ['mode' => 'hex', 'value' => $rect['borderColor']],
            ];
        }

        return $el;
    }

    /**
     * Un elemento `icon` a partir de la caja que ENGLOBA uno o más rects (los
     * fragmentos de un ícono vectorial) o imágenes `Do` de la misma celda.
     * icon_id queda en 1 (placeholder fijo) — no hay forma confiable de
     * reconocer qué ícono real del catálogo corresponde a lo detectado en el
     * PDF, se reemplaza a mano en el editor.
     */
    private static function iconBoxToElement(array $boxes, float $pageHeightPt): array
    {
        $minX = min(array_column($boxes, 'x'));
        $minY = min(array_column($boxes, 'y'));
        $maxX = max(array_map(fn($b) => $b['x'] + $b['w'], $boxes));
        $maxY = max(array_map(fn($b) => $b['y'] + $b['h'], $boxes));

        return [
            'id' => 'el-imported-' . uniqid(),
            'type' => 'icon',
            'x_cm' => round($minX / self::PT_PER_CM, 2),
            'y_cm' => round(($pageHeightPt - $maxY) / self::PT_PER_CM, 2),
            'width_cm' => round(($maxX - $minX) / self::PT_PER_CM, 2),
            'height_cm' => round(($maxY - $minY) / self::PT_PER_CM, 2),
            'z_index' => 1,
            'icon_id' => 1,
            'editable_by_customer' => false,
        ];
    }

    /**
     * Varios renglones de texto (ej. "JUAN"/"FERMIN"/"TANCO") que caen en la
     * MISMA celda se unen en un solo elemento `text`, con el contenido unido
     * por espacios — total compatibilidad con cómo ya arma/parte renglones el
     * editor nuevo (max_chars_per_line), en vez de guardar cada palabra suelta.
     */
    private static function textsToTextElement(array $lines, float $pageHeightPt, ?array $containerRect): array
    {
        usort($lines, fn($a, $b) => $b['y'] <=> $a['y']); // de arriba hacia abajo

        $content = trim(implode(' ', array_map(fn($l) => trim($l['text']), $lines)));
        $fontSizePt = $lines[0]['fontSizePt'] ?? 12.0;
        $color = $lines[0]['color'] ?? '#000000';

        $xs = array_column($lines, 'x');
        $ys = array_column($lines, 'y');
        $minX = min($xs);
        $maxY = max($ys);
        $minY = min($ys);

        // Ancho/alto del elemento: si hay una celda contenedora, se usa ESA
        // caja (con un inset chico) — mucho más preciso que estimar el ancho
        // del texto a partir de su posición de arranque. Si no hay celda
        // (texto suelto), se estima con el tamaño de fuente.
        if ($containerRect) {
            $insetPt = $fontSizePt * 0.3;
            $xCm = round(($containerRect['x'] + $insetPt) / self::PT_PER_CM, 2);
            $yCm = round(($pageHeightPt - $containerRect['y'] - $containerRect['h'] + $insetPt) / self::PT_PER_CM, 2);
            $widthCm = round(max(0.5, $containerRect['w'] - 2 * $insetPt) / self::PT_PER_CM, 2);
            $heightCm = round(max(0.5, $containerRect['h'] - 2 * $insetPt) / self::PT_PER_CM, 2);
        } else {
            $xCm = round($minX / self::PT_PER_CM, 2);
            $yCm = round(($pageHeightPt - $maxY - $fontSizePt) / self::PT_PER_CM, 2);
            $widthCm = round(max(1, strlen($content) * $fontSizePt * 0.6) / self::PT_PER_CM, 2);
            $heightCm = round(max(0.5, ($maxY - $minY + $fontSizePt * 1.3)) / self::PT_PER_CM, 2);
        }

        return [
            'id' => 'el-imported-' . uniqid(),
            'type' => 'text',
            'x_cm' => $xCm,
            'y_cm' => $yCm,
            'width_cm' => $widthCm,
            'height_cm' => $heightCm,
            'z_index' => 2,
            'content' => $content,
            'value_mode' => 'fixed', // literal, tal cual salía en el PDF original — pasarlo a dynamic_field es manual en el editor
            'font_id' => null, // sin matchear — completar a mano en el editor
            'font_size_px' => round($fontSizePt / 0.75),
            'font_weight' => 400,
            'color' => ['mode' => 'hex', 'value' => $color],
            'text_align' => 'center',
            'vertical_align' => 'middle',
            'min_lines' => 1,
            'max_lines' => max(1, count($lines)),
            'max_chars_per_line' => max(6, (int) (max(array_map('strlen', array_column($lines, 'text'))) * 1.2)),
            'editable_by_customer' => false,
        ];
    }

    /**
     * Agrupa texto suelto (sin celda) en bloques por cercanía vertical — no
     * hay un rect que lo ancle, así que se usa un umbral de salto de línea.
     */
    private static function mergeLooseTextLines(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }
        usort($texts, fn($a, $b) => $b['y'] <=> $a['y']);

        $groups = [];
        $current = [$texts[0]];
        for ($i = 1; $i < count($texts); $i++) {
            $prev = $texts[$i - 1];
            $cur = $texts[$i];
            $gap = $prev['y'] - $cur['y'];
            if ($gap <= ($cur['fontSizePt'] ?? 12) * 2.2) {
                $current[] = $cur;
            } else {
                $groups[] = $current;
                $current = [$cur];
            }
        }
        $groups[] = $current;

        return $groups;
    }

    /**
     * Saca el ancho/alto de la PRIMERA página (/MediaBox [0 0 w h]) — alcanza
     * para los PDF de una sola hoja que genera este sistema; no resuelve
     * MediaBox heredado del árbol de páginas (no hace falta acá).
     */
    private static function extractFirstMediaBox(string $raw): ?array
    {
        if (!preg_match('/\/MediaBox\s*\[\s*([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s+([\d.\-]+)\s*\]/', $raw, $m)) {
            return null;
        }
        return ['w' => (float) $m[3] - (float) $m[1], 'h' => (float) $m[4] - (float) $m[2]];
    }

    /**
     * Decodifica y concatena TODOS los content streams del PDF, en el orden
     * en que aparecen. Alcanza para los PDF de una sola página que genera
     * este sistema (si en el futuro hace falta multipágina, hay que separar
     * esto por página usando /Contents de cada /Page).
     */
    private static function extractConcatenatedContent(string $raw): string
    {
        if (!preg_match_all('/stream\r?\n(.*?)endstream/s', $raw, $m)) {
            return '';
        }

        $content = '';
        foreach ($m[1] as $s) {
            $decoded = @gzuncompress($s);
            if ($decoded === false) {
                $decoded = @zlib_decode($s);
            }
            if ($decoded === false || $decoded === null) {
                continue;
            }
            if (preg_match('/\bre\b|\bTj\b|\bTJ\b|\bDo\b/', $decoded)) {
                $content .= "\n" . $decoded;
            }
        }
        return $content;
    }
}
