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

    /**
     * Recalcula el `sig` de CADA elemento referenciado en `data.pages[].
     * grids[]` a partir del estado FINAL de `elements` — mismo paso que
     * hace el editor del front justo antes de guardar (`pages/
     * productPdfDesigns/edit/[id].vue`, el re-firmado que arma `resolved`
     * antes del POST). Hace falta correrlo acá porque `import()` arma las
     * grillas ANTES de que el controlador (`importFromProduct`) termine de
     * completar `font_id`/`icon_id`/`label_shape_id` de cada elemento — si
     * no se recalcula, el `sig` queda grabado con el `font_id`/`icon_id`
     * placeholder (null/1) que tenía en ese momento, no con el real, y el
     * editor trata CUALQUIER celda como "ya modificada a mano" desde el
     * mismo instante en que se importa (nunca coincide, ni recién abierta):
     * "Generar grilla" no vuelve a tocar ni fondo ni ícono ni texto de
     * ninguna celda al reacomodar/agregar/quitar filas o columnas.
     */
    public static function resignGrids(array $page): array
    {
        if (empty($page['grids'])) {
            return $page;
        }

        $elementsById = [];
        foreach ($page['elements'] as $el) {
            $elementsById[$el['id']] = $el;
        }

        $resign = function ($id) use ($elementsById) {
            return isset($elementsById[$id]) ? self::elementSignature($elementsById[$id]) : null;
        };

        foreach ($page['grids'] as &$grid) {
            foreach ($grid['members'] as $key => &$member) {
                foreach ($member as &$entry) {
                    if ($entry) {
                        $entry['sig'] = $resign($entry['id']) ?? $entry['sig'];
                    }
                }
                unset($entry);
            }
            unset($member);
        }
        unset($grid);

        return $page;
    }

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
        $cellRecords = [];
        $layoutGroups = [];
        $pageHeightPt = $pageSizePt['h'];

        foreach ($cellRects as $idx => $rect) {
            $cellElements = [self::rectToBackgroundElement($rect, $pageHeightPt)];
            $shape = ['background'];

            // El ícono real (imagen `Do`) tiene prioridad sobre los trazos
            // vectoriales sueltos — si hay ambos en la misma celda, es raro,
            // pero la imagen da una caja más confiable.
            $iconBoxes = !empty($imagesByCell[$idx]) ? $imagesByCell[$idx] : ($iconFragmentsByCell[$idx] ?? []);
            $iconBox = !empty($iconBoxes) ? self::combineBoxes($iconBoxes) : null;
            if ($iconBox) {
                $cellElements[] = self::iconBoxToElement($iconBoxes, $pageHeightPt);
                $shape[] = 'icon';
            }

            if (!empty($textsByCell[$idx])) {
                $cellElements[] = self::textsToTextElement($textsByCell[$idx], $pageHeightPt, $rect, $iconBox);
                $shape[] = 'text';
            }

            // "Relación" del editor (data.pages[].layout_groups) — mantiene
            // SIEMPRE la misma separación/alineación entre el ícono y su
            // nombre, recentrados en el fondo de la celda, cuando el PDF
            // real tenga un nombre más corto/largo que el de muestra (ver
            // EtiquetaService::aplicarLayoutGroups). Solo tiene sentido
            // cuando la celda tiene AMBOS (ícono y texto) — se arma con la
            // misma separación/lado que ya se detectó recién arriba para no
            // superponerlos.
            if ($iconBox && !empty($textsByCell[$idx])) {
                $layoutGroups[] = self::buildIconTextLayoutGroup(
                    $cellElements[1], // [background, icon, ...]
                    end($cellElements), // el texto siempre se agrega último arriba
                    $cellElements[0]['id']
                );
            }

            // Agrupamos fondo + ícono de UNA celda bajo un group_id
            // compartido — igual que hace el editor del front al generar una
            // grilla (ver computeGridCells) — así clickear/mover cualquiera
            // de los dos selecciona/mueve la celda entera de una. El TEXTO
            // queda a propósito AFUERA del grupo: el editor trata una
            // selección de elementos de distinto tipo como "selección
            // mixta" y ahí el panel lateral deja de mostrar la sección
            // Tipografía (ElementConfig.vue, isMixed/hasCommonColor) — si el
            // texto compartiera group_id con el fondo, un clic simple sobre
            // el texto seleccionaría los dos (fondo+texto, tipos distintos)
            // y el admin se quedaría sin poder tocar fuente/tamaño/alineación
            // de un texto recién importado sin antes desagruparlo a mano.
            $groupableElements = array_filter($cellElements, fn($el) => $el['type'] !== 'text');
            if (count($groupableElements) > 1) {
                $groupId = 'group-imported-' . uniqid();
                foreach ($cellElements as &$ce) {
                    if ($ce['type'] !== 'text') {
                        $ce['group_id'] = $groupId;
                    }
                }
                unset($ce);
            }

            $elements = array_merge($elements, $cellElements);

            $cellElement = $cellElements[0];
            $cellRecords[] = [
                'shape' => implode(',', $shape),
                'x_cm' => $cellElement['x_cm'],
                'y_cm' => $cellElement['y_cm'],
                'width_cm' => $cellElement['width_cm'],
                'height_cm' => $cellElement['height_cm'],
                'element_ids' => array_column($cellElements, 'id'),
                'elements' => $cellElements,
            ];
        }

        // Texto suelto (ej. "PEDIDO # 12345" al costado) — no se pierde,
        // queda como elemento top-level sin celda.
        foreach (self::mergeLooseTextLines($looseTexts) as $group) {
            $elements[] = self::textsToTextElement($group, $pageHeightPt, null, null, $pageSizePt['w']);
        }

        // Nota: algunas vistas legacy (ej. "maxi") dibujan el ícono y el
        // nombre como DOS celdas adyacentes, cada una con su propio fondo
        // (no una celda compartida) — a propósito NO se arma una "Relación"
        // ahí: sin un fondo en común que los contenga a los dos, el ancla
        // de la relación (el punto medio de ícono+texto) no siempre
        // reproduce exacto la separación original al recentrarse en un PDF
        // real, y terminaba superponiendo el ícono con el fondo del texto
        // (quedan sin esta mejora, igual que antes).
        $sheet = [
            'width_cm' => round($pageSizePt['w'] / self::PT_PER_CM, 2),
            'height_cm' => round($pageSizePt['h'] / self::PT_PER_CM, 2),
        ];
        if ($sheetBackgroundColor) {
            $sheet['background_color'] = ['mode' => 'hex', 'value' => $sheetBackgroundColor];
        }

        $page = [
            'id' => 'page-imported-' . uniqid(),
            'name' => 'Página importada',
            'sheet' => $sheet,
            'elements' => $elements,
        ];

        $grids = self::detectGrids($cellRecords);
        if (!empty($grids)) {
            $page['grids'] = $grids;
        }
        if (!empty($layoutGroups)) {
            $page['layout_groups'] = $layoutGroups;
        }

        return [
            'pages' => [$page],
        ];
    }

    /**
     * "Relación" de un ícono con su texto (`PdfLayoutGroup`, ver
     * useDesignEditor.ts::onCreateLayoutGroup/deriveLinks) — mismo criterio
     * que ya usa el editor del front al crear una a mano: se mide el margen
     * libre real entre el ícono y el texto en cada eje, el eje con MÁS
     * separación es el del `gap` (y ESE valor, en cm, es la distancia que
     * va a mantener siempre la relación) — el otro eje queda centrado. Así,
     * si el nombre real de una venta sale más corto o más largo que el de
     * muestra, ícono+texto se recentran juntos en vez de quedar pegados a
     * un borde (ver EtiquetaService::aplicarLayoutGroups).
     *
     * Se centran en `$containerElementId` si se pasa uno (el fondo de la
     * celda, cuando ícono y texto comparten UN mismo fondo) — si no, en el
     * punto medio de la caja que engloba a los dos (cuando cada uno tiene
     * su propio fondo por separado, ej. las vistas "maxi": ícono y nombre
     * son dos celdas adyacentes, no una compartida).
     */
    public static function buildIconTextLayoutGroup(array $icon, array $text, ?string $containerElementId = null): array
    {
        $iconLeft = $icon['x_cm'];
        $iconRight = $icon['x_cm'] + $icon['width_cm'];
        $iconTop = $icon['y_cm'];
        $iconBottom = $icon['y_cm'] + $icon['height_cm'];
        $textLeft = $text['x_cm'];
        $textRight = $text['x_cm'] + $text['width_cm'];
        $textTop = $text['y_cm'];
        $textBottom = $text['y_cm'] + $text['height_cm'];

        // Si el ícono queda a la izquierda, el margen libre entre su borde
        // derecho y el izquierdo del texto es textLeft-iconRight (positivo);
        // si en realidad el texto está a la izquierda, ese cálculo da
        // negativo y el que da positivo es el opuesto — así, sin asumir de
        // qué lado está cada uno, el de mayor valor es el que realmente los
        // separa en ese eje.
        $gapIconLeftOfText = $textLeft - $iconRight;
        $gapTextLeftOfIcon = $iconLeft - $textRight;
        $gapIconAboveText = $textTop - $iconBottom;
        $gapTextAboveIcon = $iconTop - $textBottom;

        $horizontalGap = max($gapIconLeftOfText, $gapTextLeftOfIcon);
        $verticalGap = max($gapIconAboveText, $gapTextAboveIcon);

        if ($horizontalGap >= $verticalGap) {
            $members = $gapIconLeftOfText >= $gapTextLeftOfIcon ? [$icon['id'], $text['id']] : [$text['id'], $icon['id']];
            $links = [[
                'x' => ['mode' => 'gap', 'side' => 'after', 'cm' => round(max(0, $horizontalGap), 2)],
                'y' => ['mode' => 'align', 'value' => 'center'],
            ]];
        } else {
            $members = $gapIconAboveText >= $gapTextAboveIcon ? [$icon['id'], $text['id']] : [$text['id'], $icon['id']];
            $links = [[
                'x' => ['mode' => 'align', 'value' => 'center'],
                'y' => ['mode' => 'gap', 'side' => 'after', 'cm' => round(max(0, $verticalGap), 2)],
            ]];
        }

        $group = [
            'id' => 'layout-imported-' . uniqid(),
            'members' => $members,
            'links' => $links,
        ];

        if ($containerElementId) {
            $group['container_element_id'] = $containerElementId;
        } else {
            $group['anchor'] = [
                'x_cm' => round((min($iconLeft, $textLeft) + max($iconRight, $textRight)) / 2, 2),
                'y_cm' => round((min($iconTop, $textTop) + max($iconBottom, $textBottom)) / 2, 2),
            ];
        }

        return $group;
    }

    /**
     * Agrupa las celdas detectadas en "grillas" (mismo ancho/alto y mismas
     * piezas — fondo/ícono/texto en el mismo orden) y arma el `PdfGrid` que
     * entiende el editor del front (ver "Generar grilla" en
     * useDesignEditor.ts/PdfDesignHelper.ts) — así, una vez importado el
     * diseño, se puede abrir esa herramienta para cambiar cols/rows/gaps de
     * TODAS las celdas de una, en vez de reacomodar cada una a mano.
     * No es magia: si el admin edita el contenido de una celda y la firma
     * (`sig`) deja de coincidir, el editor la trata como "modificada" y no la
     * vuelve a tocar — mismo comportamiento que tendría una grilla generada
     * a mano en el front.
     */
    private static function detectGrids(array $cellRecords): array
    {
        $groups = [];
        foreach ($cellRecords as $cell) {
            $key = $cell['shape'] . '|' . round($cell['width_cm'], 1) . 'x' . round($cell['height_cm'], 1);
            $groups[$key][] = $cell;
        }

        $grids = [];
        foreach ($groups as $cells) {
            if (count($cells) < 2) {
                continue;
            }

            $xs = array_values(array_unique(array_map(fn($c) => $c['x_cm'], $cells)));
            $ys = array_values(array_unique(array_map(fn($c) => $c['y_cm'], $cells)));
            sort($xs);
            sort($ys);
            if (count($xs) < 2 && count($ys) < 2) {
                continue; // ni una fila ni una columna repetida: no es una grilla.
            }

            $colOf = fn($x) => array_search($x, $xs, true);
            $rowOf = fn($y) => array_search($y, $ys, true);

            $templateIdx = 0;
            foreach ($cells as $i => $c) {
                $cur = [$rowOf($c['y_cm']), $colOf($c['x_cm'])];
                $best = [$rowOf($cells[$templateIdx]['y_cm']), $colOf($cells[$templateIdx]['x_cm'])];
                if ($cur < $best) {
                    $templateIdx = $i;
                }
            }
            $template = $cells[$templateIdx];
            $templateCol = $colOf($template['x_cm']);
            $templateRow = $rowOf($template['y_cm']);

            $members = [];
            foreach ($cells as $i => $c) {
                if ($i === $templateIdx) {
                    continue;
                }
                $offsetCol = $colOf($c['x_cm']) - $templateCol;
                $offsetRow = $rowOf($c['y_cm']) - $templateRow;
                $members["{$offsetCol},{$offsetRow}"] = array_map(
                    fn($el) => ['id' => $el['id'], 'sig' => self::elementSignature($el)],
                    $c['elements']
                );
            }

            $gapX = count($xs) > 1 ? round($xs[1] - $xs[0] - $template['width_cm'], 2) : 0;
            $gapY = count($ys) > 1 ? round($ys[1] - $ys[0] - $template['height_cm'], 2) : 0;

            $grids[] = [
                'id' => 'grid-imported-' . uniqid(),
                'cols' => count($xs),
                'rows' => count($ys),
                'gap_x_cm' => $gapX,
                'gap_y_cm' => $gapY,
                'template' => $template['element_ids'],
                'members' => $members,
            ];
        }

        return $grids;
    }

    /**
     * Replica PdfDesignHelper.elementSignature() del front (ver
     * useDesignEditor.ts/PdfDesignHelper.ts) para que el `sig` de cada celda
     * sea compatible con el chequeo de "¿esta celda sigue intacta?" que hace
     * "Generar grilla" al reescalar. Mismo algoritmo (normalizar -> ordenar
     * claves -> JSON.stringify -> djb2 en base36), con una limitación
     * conocida: el front itera la cadena como code units UTF-16
     * (`charCodeAt`) y acá se reproduce sobre codepoints Unicode reales — da
     * EXACTAMENTE el mismo resultado para texto dentro del BMP (todo lo que
     * generamos: números, hex, español sin emoji/símbolos raros), pero no
     * está garantizado para caracteres fuera del BMP. Si el hash no
     * coincidiera por eso, el peor caso es que el editor trate esa celda
     * como "ya modificada" y no la toque al reescalar — no rompe ni pierde
     * nada, solo pierde esa comodidad puntual.
     */
    public static function elementSignature(array $el): string
    {
        unset($el['id'], $el['group_id']);
        $json = json_encode(self::normalizeForSignature($el), JSON_UNESCAPED_UNICODE);
        $json = str_replace('\\/', '/', $json); // JSON.stringify no escapa "/".

        $hash = 5381;
        $len = mb_strlen($json, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $codepoint = mb_ord(mb_substr($json, $i, 1, 'UTF-8'), 'UTF-8');
            $hash = self::to32BitSigned(($hash << 5) + $hash + $codepoint);
        }

        $unsigned = $hash < 0 ? $hash + 0x100000000 : $hash;
        return base_convert((string) $unsigned, 10, 36);
    }

    private static function to32BitSigned(int $n): int
    {
        $n = $n & 0xFFFFFFFF;
        return $n > 0x7FFFFFFF ? $n - 0x100000000 : $n;
    }

    /**
     * value !== null && value !== undefined && value !== '' + orden de
     * claves alfabético + recursivo — igual que el `normalize` del front.
     */
    private static function normalizeForSignature($value)
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map([self::class, 'normalizeForSignature'], $value);
            }
            $filtered = array_filter($value, fn($v) => $v !== null && $v !== '');
            ksort($filtered, SORT_STRING);
            $result = [];
            foreach ($filtered as $k => $v) {
                $result[$k] = self::normalizeForSignature($v);
            }
            return empty($result) ? new \stdClass() : $result;
        }
        return $value;
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
        // Si el PDF original pintó este rect con el operador CMYK `k` (no RGB
        // `rg`), se guarda el CMYK tal cual en vez de convertirlo a hex: un
        // visor de PDF con manejo de color le aplica a `k` un perfil que lo
        // ve bien más apagado que la conversión matemática simple que usamos
        // para el hex (ver PdfContentStreamParser::cmykToHex) — para que el
        // fondo importado se vea IDÉNTICO al original (no solo "matemáticamente
        // equivalente") hay que reproducir el mismo operador `k`, no un RGB.
        $color = !empty($rect['colorCmyk'])
            ? ['mode' => 'cmyk', 'value' => $rect['colorCmyk']]
            : ['mode' => 'hex', 'value' => $rect['color']];

        $el = [
            'id' => 'el-imported-' . uniqid(),
            'type' => 'background',
            'x_cm' => round($rect['x'] / self::PT_PER_CM, 2),
            'y_cm' => round(($pageHeightPt - $rect['y'] - $rect['h']) / self::PT_PER_CM, 2),
            'width_cm' => round($rect['w'] / self::PT_PER_CM, 2),
            'height_cm' => round($rect['h'] / self::PT_PER_CM, 2),
            'z_index' => 0,
            'color' => $color,
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
        $box = self::combineBoxes($boxes);

        return [
            'id' => 'el-imported-' . uniqid(),
            'type' => 'icon',
            'x_cm' => round($box['x'] / self::PT_PER_CM, 2),
            'y_cm' => round(($pageHeightPt - $box['y'] - $box['h']) / self::PT_PER_CM, 2),
            'width_cm' => round($box['w'] / self::PT_PER_CM, 2),
            'height_cm' => round($box['h'] / self::PT_PER_CM, 2),
            'z_index' => 1,
            'icon_id' => 1,
            'editable_by_customer' => false,
        ];
    }

    /**
     * Caja en puntos PDF (origen abajo-izquierda, como el resto de las
     * primitivas) que ENGLOBA una o más cajas (rects/imágenes).
     */
    private static function combineBoxes(array $boxes): array
    {
        $minX = min(array_column($boxes, 'x'));
        $minY = min(array_column($boxes, 'y'));
        $maxX = max(array_map(fn($b) => $b['x'] + $b['w'], $boxes));
        $maxY = max(array_map(fn($b) => $b['y'] + $b['h'], $boxes));

        return ['x' => $minX, 'y' => $minY, 'w' => $maxX - $minX, 'h' => $maxY - $minY];
    }

    /**
     * Varios renglones de texto (ej. "JUAN"/"FERMIN"/"TANCO") que caen en la
     * MISMA celda se unen en un solo elemento `text`, con el contenido unido
     * por espacios — total compatibilidad con cómo ya arma/parte renglones el
     * editor nuevo (max_chars_per_line), en vez de guardar cada palabra suelta.
     */
    private static function textsToTextElement(array $lines, float $pageHeightPt, ?array $containerRect, ?array $iconBox = null, ?float $pageWidthPt = null): array
    {
        // Texto girado (ej. "PEDIDO # 12345" al costado de la hoja, ver
        // public/css/etiquetas.css ".numeroOrder p { transform:
        // rotate(270deg) }") — el cálculo normal de abajo asume texto
        // horizontal, y para texto realmente girado ~90°/270° el "origen"
        // que da el PDF puede caer bien lejos de donde se ve el texto
        // (incluso fuera de la hoja) si se lo trata como si no estuviera
        // girado. En las vistas legacy esto SOLO pasa con texto suelto
        // (nunca dentro de una celda), por eso se chequea acá.
        if ($containerRect === null) {
            $angleDeg = (float) ($lines[0]['angleDeg'] ?? 0.0);
            if (abs($angleDeg) > 1 && abs($angleDeg) < 179) {
                return self::rotatedTextElement($lines, $angleDeg, $pageHeightPt, $pageWidthPt ?? $pageHeightPt);
            }
        }

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
            // Todo en coordenadas PDF (origen abajo-izquierda, Y crece hacia
            // arriba) hasta el final — recién ahí se convierte a cm con
            // origen arriba-izquierda, igual que el resto del archivo.
            $insetPt = $fontSizePt * 0.3;
            $xLeft = $containerRect['x'] + $insetPt;
            $xRight = $containerRect['x'] + $containerRect['w'] - $insetPt;
            $yBottomEdge = $containerRect['y'] + $insetPt;
            $yTopEdge = $containerRect['y'] + $containerRect['h'] - $insetPt;

            // Las vistas legacy dibujan el ícono y el texto como columnas (o
            // filas) separadas de una tabla — nunca superpuestos. Si esta
            // celda tiene ícono, hay que recortarle al texto el mismo
            // espacio que ocupaba esa columna/fila en el HTML original, si
            // no el texto (centrado en TODA la celda) queda pasando por
            // encima del dibujo. El ícono casi nunca ocupa la mayoría del
            // ancho/alto de la celda (suele ser chico, en una esquina), así
            // que en vez de un umbral de tamaño se compara el margen libre
            // de cada lado: el lado con MENOS margen es donde está "pegado"
            // el ícono (esquina de icon-left/icon-top/etc.), y el texto va
            // del otro lado. Se usa el eje (horizontal o vertical) donde esa
            // diferencia de márgenes es más marcada.
            if ($iconBox) {
                $leftGap = $iconBox['x'] - $containerRect['x'];
                $rightGap = ($containerRect['x'] + $containerRect['w']) - ($iconBox['x'] + $iconBox['w']);
                $topGap = ($containerRect['y'] + $containerRect['h']) - ($iconBox['y'] + $iconBox['h']);
                $bottomGap = $iconBox['y'] - $containerRect['y'];

                if (abs($leftGap - $rightGap) >= abs($topGap - $bottomGap)) {
                    if ($leftGap < $rightGap) {
                        // Ícono pegado a la izquierda -> texto a la derecha.
                        $xLeft = max($xLeft, $iconBox['x'] + $iconBox['w'] + $insetPt);
                    } else {
                        $xRight = min($xRight, $iconBox['x'] - $insetPt);
                    }
                } else {
                    if ($topGap < $bottomGap) {
                        // Ícono pegado arriba -> texto abajo.
                        $yTopEdge = min($yTopEdge, $iconBox['y'] - $insetPt);
                    } else {
                        $yBottomEdge = max($yBottomEdge, $iconBox['y'] + $iconBox['h'] + $insetPt);
                    }
                }
            }

            $xCm = round($xLeft / self::PT_PER_CM, 2);
            $yCm = round(($pageHeightPt - $yTopEdge) / self::PT_PER_CM, 2);
            $widthCm = round(max(0.5, $xRight - $xLeft) / self::PT_PER_CM, 2);
            $heightCm = round(max(0.5, $yTopEdge - $yBottomEdge) / self::PT_PER_CM, 2);
        } else {
            // Si este texto suelto se armó juntando VARIOS renglones del PDF
            // original (ej. "NOMBRE" + "APELLIDO", cada uno su propia línea
            // debajo del ícono en las celdas "spot"), el ancho real que
            // ocupa es el del renglón más largo de los dos — no el de la
            // suma de ambos pegados con un espacio en `$content`. Medir por
            // `strlen($content)` como si fuera un solo renglón daba una caja
            // mucho más ancha que lo que en verdad se ve (el nombre se sale
            // del círculo "Spot" al editarlo, aunque al generar el PDF real
            // (EtiquetaService) sí se parte en renglones y entra bien).
            $longestLineLength = max(array_map(fn($l) => strlen(trim($l['text'])), $lines));
            $xCm = round($minX / self::PT_PER_CM, 2);
            $yCm = round(($pageHeightPt - $maxY - $fontSizePt) / self::PT_PER_CM, 2);
            $widthCm = round(max(1, $longestLineLength * $fontSizePt * 0.6) / self::PT_PER_CM, 2);
            $heightCm = round(max(0.5, ($maxY - $minY + $fontSizePt * 1.3)) / self::PT_PER_CM, 2);
        }

        // Interlineado real: se mide directo de la distancia entre
        // renglones de ESTE texto en el PDF de origen — no hace falta
        // reconstruir la regla CSS "line-height: N" de la vista legacy. La
        // corrección por métricas de fuente que aplica el backend al
        // generar un PDF real (ver EtiquetaService::resolverElementoDesign,
        // $fontRatio) existe justamente para que "line_height: N" reproduzca
        // SIEMPRE el mismo espacio visual (N veces el tamaño de fuente), sin
        // importar qué fuente tenga asignada el elemento — así que el valor
        // a guardar es, literalmente, la distancia entre renglones (en pt)
        // dividida por el tamaño de fuente (en pt), tal cual se ve acá.
        $lineHeight = null;
        if (count($lines) > 1) {
            $gaps = [];
            for ($i = 1; $i < count($lines); $i++) {
                $gaps[] = $lines[$i - 1]['y'] - $lines[$i]['y'];
            }
            $avgGapPt = array_sum($gaps) / count($gaps);
            $lineHeight = round(max(0.5, min(5, $avgGapPt / $fontSizePt)), 2);
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
            'font_value_mode' => 'fixed',
            'font_size_px' => round($fontSizePt / 0.75),
            'font_weight' => 400,
            'color' => ['mode' => 'hex', 'value' => $color],
            'text_align' => 'center',
            'vertical_align' => 'middle',
            'vertical_offset_cm' => self::computeVerticalOffsetCm($heightCm, count($lines), $fontSizePt, $lineHeight),
            'line_height' => $lineHeight,
            'min_lines' => 1,
            'max_lines' => max(1, count($lines)),
            'max_chars_per_line' => max(6, (int) (max(array_map('strlen', array_column($lines, 'text'))) * 1.2)),
            'editable_by_customer' => false,
        ];
    }

    /**
     * Replica `PdfDesignHelper.resolveVerticalOffsetCm()` del front — el
     * editor lo recalcula y lo "estampa" en CADA guardado (ver
     * `pages/productPdfDesigns/edit/[id].vue`), y ESE campo entra en el
     * cálculo de `sig` (la firma que usa "Generar grilla" para saber si una
     * celda sigue intacta) — si el importador no lo deja ya seteado como lo
     * haría el editor, la firma de un texto recién importado no coincide
     * con la que el editor calcula apenas lo carga (aunque nadie haya
     * tocado nada todavía), y "Generar grilla" trata esa celda como
     * "modificada": mueve el fondo (sin este campo extra) pero deja el
     * texto sin tocar. Acá, en vez de reimplementar el ajuste de renglones
     * por ancho de caracteres del front, se usa directo la cantidad de
     * renglones YA conocida (la del PDF real), que es lo que ese ajuste
     * tendría que reproducir de todos modos.
     */
    private static function computeVerticalOffsetCm(float $heightCm, int $lineCount, float $fontSizePt, ?float $lineHeight): float
    {
        $fontSizePx = $fontSizePt / 0.75;
        $effectiveLineHeight = $lineHeight ?? 1.15; // PDF_DEFAULT_LINE_HEIGHT del front
        $lineCount = max(1, $lineCount);
        $cmPerPx = 2.54 / 96;
        $textBlockHeightCm = $lineCount * $fontSizePx * $effectiveLineHeight * $cmPerPx;
        $slackCm = max(0, $heightCm - $textBlockHeightCm);

        return round($slackCm / 2, 2); // vertical_align siempre 'middle' en este importador
    }

    /**
     * Texto suelto girado (ver nota en textsToTextElement). El editor rota
     * un elemento alrededor del CENTRO de su propia caja (ver
     * `rotation_deg` + `transform: rotate()` en RENDER.blade.php) — así que
     * acá se reconstruye la caja SIN girar (mismo ancho/alto que tendría
     * ese mismo texto horizontal) centrada en el punto que resulta de girar
     * su centro local alrededor del origen real que dio el PDF. Al
     * aplicarle después `rotation_deg` en el editor, el texto cae
     * visualmente en el mismo lugar que en el PDF original.
     * En la práctica este caso es siempre UN solo renglón (un único Td+TJ
     * en el PDF, ver "PEDIDO #") — no vale la pena generalizar el apilado
     * vertical de varios renglones girados, que no ocurre acá.
     */
    private static function rotatedTextElement(array $lines, float $angleDegPdf, float $pageHeightPt, float $pageWidthPt): array
    {
        $line = $lines[0];
        $content = trim(implode(' ', array_map(fn($l) => trim($l['text']), $lines)));
        $fontSizePt = $line['fontSizePt'] ?? 12.0;
        $color = $line['color'] ?? '#000000';

        $estWidthPt = max(1, strlen($content) * $fontSizePt * 0.6);
        $estHeightPt = $fontSizePt * 1.3;

        $angleRad = deg2rad($angleDegPdf);
        $cos = cos($angleRad);
        $sin = sin($angleRad);

        // Centro de la caja SIN girar, en espacio local de texto (origen en
        // el punto de arranque del renglón que da el PDF), rotado al mismo
        // espacio absoluto de página (Y hacia arriba) que usa ese origen.
        $localCenterX = $estWidthPt / 2;
        $localCenterY = 0.5 * $fontSizePt;
        $centerX = $line['x'] + $localCenterX * $cos - $localCenterY * $sin;
        $centerY = $line['y'] + $localCenterX * $sin + $localCenterY * $cos;

        $widthCm = max(0.5, round($estWidthPt / self::PT_PER_CM, 2));
        $heightCm = max(0.5, round($estHeightPt / self::PT_PER_CM, 2));
        $centerXCm = $centerX / self::PT_PER_CM;
        $centerYCm = ($pageHeightPt - $centerY) / self::PT_PER_CM; // Y hacia abajo desde arriba de la hoja

        // `rotation_deg` que espera el editor: convención CSS (horaria, Y
        // hacia abajo) — el ángulo que da el PDF está en la convención
        // matemática contraria (antihoraria, Y hacia arriba): es la MISMA
        // rotación vista desde el otro eje, se invierte el signo.
        $rotationDeg = -$angleDegPdf;
        if ($rotationDeg > 180) {
            $rotationDeg -= 360;
        } elseif ($rotationDeg <= -180) {
            $rotationDeg += 360;
        }

        // El ancho/alto de arriba son una ESTIMACIÓN (por cantidad de
        // caracteres, no la fuente real) — con texto girado ~90°, cualquier
        // error ahí se nota más (empuja el centro a lo largo del lado LARGO
        // de la caja) y puede terminar pegando el texto justo al borde de
        // la hoja, o incluso un poco afuera. Se recorta el centro para que
        // la caja YA GIRADA (su AABB real, no la caja sin girar) quede
        // siempre adentro con un margen chico — mismo criterio que un
        // margen de seguridad de impresión, no afecta el caso normal (sin
        // rotación esto no se usa).
        $marginCm = 0.3;
        $sheetWidthCm = $pageWidthPt / self::PT_PER_CM;
        $sheetHeightCm = $pageHeightPt / self::PT_PER_CM;
        $halfWidthRotatedCm = ($widthCm / 2) * abs($cos) + ($heightCm / 2) * abs($sin);
        $halfHeightRotatedCm = ($widthCm / 2) * abs($sin) + ($heightCm / 2) * abs($cos);

        $centerXCm = self::clampCenter($centerXCm, $halfWidthRotatedCm, $sheetWidthCm, $marginCm);
        $centerYCm = self::clampCenter($centerYCm, $halfHeightRotatedCm, $sheetHeightCm, $marginCm);

        return [
            'id' => 'el-imported-' . uniqid(),
            'type' => 'text',
            'x_cm' => round($centerXCm - $widthCm / 2, 2),
            'y_cm' => round($centerYCm - $heightCm / 2, 2),
            'width_cm' => $widthCm,
            'height_cm' => $heightCm,
            'rotation_deg' => round($rotationDeg, 1),
            'z_index' => 2,
            'content' => $content,
            'value_mode' => 'fixed',
            'font_id' => null,
            'font_value_mode' => 'fixed',
            'font_size_px' => round($fontSizePt / 0.75),
            'font_weight' => 400,
            'color' => ['mode' => 'hex', 'value' => $color],
            'text_align' => 'center',
            'vertical_align' => 'middle',
            'vertical_offset_cm' => self::computeVerticalOffsetCm($heightCm, 1, $fontSizePt, null),
            'min_lines' => 1,
            'max_lines' => 1,
            'max_chars_per_line' => max(6, (int) (strlen($content) * 1.2)),
            'editable_by_customer' => false,
        ];
    }

    /**
     * Centro (en un solo eje) recortado para que un segmento de `$halfExtent`
     * de radio alrededor suyo quede adentro de `[0, $axisLengthCm]` con
     * `$marginCm` de aire de cada lado — si el segmento es más grande que la
     * hoja (margen incluido), se lo centra en el medio en vez de forzar un
     * recorte imposible.
     */
    private static function clampCenter(float $centerCm, float $halfExtentCm, float $axisLengthCm, float $marginCm): float
    {
        $min = $halfExtentCm + $marginCm;
        $max = $axisLengthCm - $halfExtentCm - $marginCm;
        if ($min > $max) {
            return $axisLengthCm / 2;
        }
        return max($min, min($max, $centerCm));
    }

    /**
     * Ensancha un elemento `text` YA armado (por `import()`) para que un
     * CONTENIDO NUEVO —más largo que el original— entre en una sola línea.
     * Pensado para `ProductPdfDesignController::importFromProduct()`, que
     * reemplaza el número de muestra de "PEDIDO # ..." por el placeholder
     * `{{numero_pedido}}` (más largo) DESPUÉS de que `import()` ya calculó
     * la geometría con el número de muestra original (más corto) — sin este
     * reajuste, `max_chars_per_line` quedaba corto para el contenido final
     * y, si el elemento está girado, la caja además quedaba angosta para su
     * lado largo. Mantiene el mismo CENTRO y la misma rotación, solo
     * agranda el ancho (nunca lo achica) y vuelve a recortarlo dentro de la
     * hoja con el mismo margen que usa `rotatedTextElement()`. No hace nada
     * si el contenido nuevo ya entraba en el ancho actual.
     */
    public static function resizeTextElementForContent(array $el, string $newContent, float $sheetWidthCm, float $sheetHeightCm): array
    {
        $el['max_chars_per_line'] = max($el['max_chars_per_line'] ?? 6, (int) (strlen($newContent) * 1.2));

        $fontSizePt = ((float) ($el['font_size_px'] ?? 12)) * 0.75;
        $estWidthPt = max(1, strlen($newContent) * $fontSizePt * 0.6);
        $newWidthCm = max(0.5, round($estWidthPt / self::PT_PER_CM, 2));

        $widthCm = (float) ($el['width_cm'] ?? $newWidthCm);
        $heightCm = (float) ($el['height_cm'] ?? 0.5);
        if ($newWidthCm <= $widthCm) {
            return $el;
        }

        $centerXCm = $el['x_cm'] + $widthCm / 2;
        $centerYCm = $el['y_cm'] + $heightCm / 2;

        $angleRad = deg2rad((float) ($el['rotation_deg'] ?? 0));
        $cos = cos($angleRad);
        $sin = sin($angleRad);
        $marginCm = 0.3;
        $halfWidthRotatedCm = ($newWidthCm / 2) * abs($cos) + ($heightCm / 2) * abs($sin);
        $halfHeightRotatedCm = ($newWidthCm / 2) * abs($sin) + ($heightCm / 2) * abs($cos);

        $centerXCm = self::clampCenter($centerXCm, $halfWidthRotatedCm, $sheetWidthCm, $marginCm);
        $centerYCm = self::clampCenter($centerYCm, $halfHeightRotatedCm, $sheetHeightCm, $marginCm);

        $el['width_cm'] = $newWidthCm;
        $el['x_cm'] = round($centerXCm - $newWidthCm / 2, 2);
        $el['y_cm'] = round($centerYCm - $heightCm / 2, 2);

        return $el;
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

        // Dos textos sueltos son del MISMO bloque solo si están cerca EN
        // SIMULTÁNEO en X y en Y — no alcanza con que compartan altura (eso
        // se probó con un ordenamiento global por X como paso previo, y se
        // rompía: varias vistas legacy repiten una celda SIN fondo propio
        // en la MISMA fila (ej. la fila "spot" de stars/spot-and-super-mini
        // — un ícono + "NOMBRE"/"APELLIDO" por celda, sin rect de fondo
        // detrás, ver findSmallestContainingRect) Y la página puede tener
        // OTRA sección con su propia grilla de columnas en otro rango de Y
        // — si el espaciado entre columnas de una sección coincide por
        // casualidad con el de la otra, ordenar TODO por X mezclaba texto
        // de dos secciones distintas (lejísimos en Y) en una sola columna.
        // Acá se arman "componentes conectados": cada texto se une a
        // cualquier otro que esté a la vez cerca en X (una celda vecina
        // está mucho más lejos que el salto de línea dentro de la MISMA
        // celda) y en Y (el salto de línea normal) — sin im portar en qué
        // orden se los recorra ni qué otras columnas/secciones haya en el
        // medio.
        $n = count($texts);
        $parent = range(0, $n - 1);
        $find = function (int $i) use (&$find, &$parent) {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }
            return $i;
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $texts[$i];
                $b = $texts[$j];
                $fontSize = max($a['fontSizePt'] ?? 12, $b['fontSizePt'] ?? 12);
                $gapX = abs($a['x'] - $b['x']);
                $gapY = abs($a['y'] - $b['y']);
                if ($gapX <= max($fontSize * 6, 40) && $gapY <= $fontSize * 2.2) {
                    $ri = $find($i);
                    $rj = $find($j);
                    if ($ri !== $rj) {
                        $parent[$ri] = $rj;
                    }
                }
            }
        }

        $groupsByRoot = [];
        foreach ($texts as $i => $t) {
            $groupsByRoot[$find($i)][] = $t;
        }

        return array_values($groupsByRoot);
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
