<?php

namespace App\Services;

use App\Models\LabelShape;
use App\Models\PersonalizationIcon;
use App\Models\ProductPdfDesign;
use App\Models\Typography;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class EtiquetaService
{
    private const CM_TO_PT = 72 / 2.54;

    /**
     * 🗑️ Elimina todos los PDFs existentes de un pedido específico
     */
    public static function limpiarPdfsDelPedido(int $ventaId, $fechaCompra = null): void
    {
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
        $dirPath = storage_path("app/pdf/planchas/{$fechaCarpeta}");

        if (!is_dir($dirPath)) {
            return; // No hay carpeta, no hay nada que eliminar
        }

        $existingFiles = glob("{$dirPath}/{$ventaId}-*.pdf");
        if (!empty($existingFiles)) {
            foreach ($existingFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    Log::info("🗑️ PDF anterior eliminado", ['path' => $file]);
                }
            }
            Log::info("🔄 Eliminados " . count($existingFiles) . " PDFs anteriores del pedido {$ventaId}");
        }
    }

    public static function generarEtiquetas(int $ventaId, $tematicaId, array $nombres, $productOrder, $tematicaCoincidente, $customColor, $customIcon, $fechaCompra = null, array $firstNames = []): array
    {
        $logo = "https://api.etiquecosaslab.com.ar/icons/mail/etiquecosas_logo-rosa.png";
        $outputFiles = [];
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
        $dirPath = storage_path("app/pdf/planchas/{$fechaCarpeta}");
        if (!is_dir($dirPath)) mkdir($dirPath, 0755, true);

        $pdf = $tematicaCoincidente['pdf'] ?? null;
        $tematicaName = $tematicaCoincidente['name'] ?? null;
        $colorRange = $tematicaCoincidente['color-range'] ?? null;
        $imagesPdf = $tematicaCoincidente['images'] ?? null;
        $urlPdf = $tematicaCoincidente['pdf-url'] ?? null;
        $typography = $tematicaCoincidente['typography'] ?? null;
        $numberLabels = $tematicaCoincidente['number-labels'] ?? null;
        $numberColumn = $tematicaCoincidente['number-columns'] ?? null;


        Log::info("columnaaaaaa");
        Log::info($numberColumn);

        Log::info("nombreeee");
        Log::info($nombres);

        /**
         * 🔧 Helper para obtener vistas según tipo o URL personalizada
         */
        $getViews = function ($pdf, string $prefix, $urlPdf = null) use ($customIcon) {
            // 🟣 Si llegan URLs personalizadas desde la web
            if ($urlPdf) {
                // Acepta una o varias rutas
                $urls = is_array($urlPdf) ? $urlPdf : [$urlPdf];
                // Asegura el prefijo "tematica/" si no lo tiene
                return array_map(function ($u) use ($customIcon) {
                    $path = str_starts_with($u, 'tematica/') ? $u : "tematica/{$u}";

                    // 🔄 Si no hay icono y la ruta contiene "PERSONALIZABLE", cambiar a "PERSONALIZABLE SIN ICONO"
                    if (!$customIcon && str_contains($path, '/PERSONALIZABLE')) {
                        $path = str_replace('/PERSONALIZABLE', '/PERSONALIZABLE SIN ICONO', $path);
                    }

                    return $path;
                }, $urls);
            }

            // 🟢 Map clásico de PDFs
            /* $map = [
                'Etiquetas maxi, verticales, super-maxi, super-mini' => "tematica/principal/$prefix",
                'Etiquetas vinilo' => "tematica/vinilo/$prefix",
                'Etiquetas super-mini' => "tematica/super-mini/$prefix",
                'Etiquetas super-maxi' => "tematica/super-maxi/$prefix",
                'Etiquetas maxi' => "tematica/maxi/$prefix",
                'Etiquetas spot and maxi' => "tematica/spot-and-maxi/$prefix",
                'Etiquetas maxi and super maxi and super mini' => "tematica/maxi-and-super-maxi-and-super-mini/$prefix",
                'Etiquetas planchables' => "tematica/planchable/$prefix",
                'Etiquetas transfer' => "tematica/transfer/$prefix",
            ];

            if ($pdf) {
                return array_values(array_intersect_key($map, array_flip($pdf)));
            } */

            // 🟠 Vistas por defecto si no hay coincidencias
            $prefixLimpia = self::limpiarNombreArchivo($prefix);
            $prefixLimpia = strtoupper($prefixLimpia);
            return [
                "tematica/principal/$prefixLimpia",
                "tematica/vinilo/$prefixLimpia",
                "tematica/super-mini/$prefixLimpia",
            ];
        };

        /**
         * 🧩 Helper para generar y guardar un PDF
         */
        $renderPdf = function ($view, $plantilla, $product_order, $filePath) use (&$outputFiles) {
            try {
                $pdf = Pdf::loadView($view, compact('plantilla', 'product_order'))->setPaper('a4', 'portrait');
                $dompdf = $pdf->getDomPDF();
                $dompdf->getOptions()->setFontDir(public_path('fonts'));
                $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));
                $pdf->save($filePath);
                $outputFiles[] = $filePath;
                Log::info("✅ PDF generado", ['path' => $filePath]);
            } catch (\Throwable $e) {
                Log::error("❌ Error generando PDF", [
                    'view' => $view,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        /**
         * 🟣 CASO 1: PDF PERSONALIZABLE (CON ICONO)
         */
        if ($customIcon && $customColor && !$colorRange) {
            Log::info("🟣 Generando PDF PERSONALIZABLE con icono");
            $views = $getViews($pdf, "PERSONALIZABLE", $urlPdf);
            $customIconPath = public_path($customIcon);

            foreach ($nombres as $idx => $nombre) {
                $nombreLength = mb_strlen($nombre, 'UTF-8');
                $fontClass = $nombreLength > 20 ? 'extra-small-text-size' : ($nombreLength > 16 ? 'small-text-size' : 'normal-text-size');
                $plantilla = [
                    'colores' => $customColor[0],
                    'imagen' => $customIconPath,
                    'fontClass' => $fontClass,
                    'columna' => $numberColumn ?? 2,
                    'filas' => 19,
                    'label' => $numberLabels ?? 24
                ];
                $product_order = (object)['name' => $nombre, 'firstName' => $firstNames[$idx] ?? null, 'order' => (object)['id_external' => $ventaId]];

                foreach ($views as $i => $view) {
                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-PERSONALIZABLE-" . ($i + 1) . ".pdf";
                    $renderPdf($view, $plantilla, $product_order, $filePath);
                }
            }
            return $outputFiles;
        }

        /**
         * 🟣 CASO 1B: PDF PERSONALIZABLE SIN ICONO (LISA)
         */
        if (!$customIcon && $customColor && !$colorRange) {
            Log::info("🟣 Generando PDF PERSONALIZABLE sin icono (lisa)");
            $views = $getViews($pdf, "PERSONALIZABLE SIN ICONO", $urlPdf);

            foreach ($nombres as $idx => $nombre) {
                $nombreLength = mb_strlen($nombre, 'UTF-8');
                $fontClass = $nombreLength > 20 ? 'extra-small-text-size' : ($nombreLength > 16 ? 'small-text-size' : 'normal-text-size');

                $plantilla = [
                    'colores' => $customColor[0],
                    'fontClass' => $fontClass,
                    'columna' => $numberColumn ?? 2,
                    'filas' => 19,
                    'label' => $numberLabels ?? 24
                ];
                $product_order = (object)['name' => $nombre, 'firstName' => $firstNames[$idx] ?? null, 'order' => (object)['id_external' => $ventaId]];

                foreach ($views as $i => $view) {
                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-PERSONALIZABLE_SIN_ICONO-" . ($i + 1) . ".pdf";
                    $renderPdf($view, $plantilla, $product_order, $filePath);
                }
            }
            return $outputFiles;
        }

        /**
         * 🟢 CASO 2: PDF GAMA DE COLORES
         */
        if ($colorRange) {
            Log::info("🟢 Generando PDF GAMA DE COLORES");
            $views = $getViews($pdf, "COLOR RANGE", $urlPdf);

            foreach ($nombres as $idx => $nombre) {
                $isWhiteAndBlack = $tematicaName === 'Blanco y Negro';
                $isWhite = $tematicaName === 'Blanco' || $tematicaName === 'Blanco y Negro' ? true : null;
                $nombreLength = mb_strlen($nombre, 'UTF-8');
                $fontClass = $nombreLength > 20 ? 'extra-small-text-size' : ($nombreLength > 16 ? 'small-text-size' : 'normal-text-size');

                $plantilla = [
                    'colores' => $isWhiteAndBlack ? ["#FFF", "#FFF", "#FFF", "#FFF", "#FFF", "#FFF"] : $colorRange,
                    'color' => $colorRange,
                    'images' => $imagesPdf ? array_map(fn($img) => storage_path("app/pdf/Iconos/Tematicas/$img"), $imagesPdf) : [],
                    'colorText' => $isWhiteAndBlack ? '#000' : '#fff',
                    'fontClass' => $fontClass,
                    'fontSize' => $typography ? self::getFontSize($nombre, $typography) : null,
                    'logo' => $logo,
                    'filas' => 19,
                    'label' => $numberLabels ?? 24,
                    'isWhite' => $isWhite ?? null
                ];

                $product_order = (object)['name' => $nombre, 'firstName' => $firstNames[$idx] ?? null, 'order' => (object)['id_external' => $ventaId]];

                foreach ($views as $i => $view) {
                    $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-COLOR_RANGE-" . ($i + 1) . ".pdf";
                    $renderPdf($view, $plantilla, $product_order, $filePath);
                }
            }
            return $outputFiles;
        }

        /**
         * 🟢 CASO 3: PDF NORMAL CON TEMÁTICA
         */
        $attributeValue = DB::table('attribute_values')->find($tematicaId);
        if (!$attributeValue) throw new \Exception("Temática no encontrada: {$tematicaId}");
        $tematica = strtoupper($attributeValue->value);
        Log::info("🔹 Temática encontrada: {$tematica}");

        $tematicaDb = DB::table('tematicas')->where('name', $attributeValue->value)->first();
        $colores = !empty($tematicaDb->colors) ? json_decode($tematicaDb->colors, true) ?: [] : [];

        // columnas
        $columna = $numberColumn ?? 2;
        foreach (['columna', 'columns', 'cols'] as $f) {
            if (isset($tematicaDb->$f) && is_numeric($tematicaDb->$f)) {
                $columna = (int) $tematicaDb->$f;
                break;
            }
        }

        // 🧹 Limpiar temática: remover acentos y caracteres especiales para la ruta
        $tematicaLimpia = self::limpiarNombreArchivo($tematica);
        $tematicaLimpia = strtoupper($tematicaLimpia);

        // imágenes
        $iconosPath = storage_path("app/pdf/Iconos/Tematicas/{$tematicaLimpia}");
        $imagenes = [];
        if (is_dir($iconosPath)) {
            foreach (scandir($iconosPath) as $f) {
                if (preg_match('/\.(png|jpg|jpeg|svg)$/i', $f)) {
                    $imagenes[] = $iconosPath . DIRECTORY_SEPARATOR . $f;
                }
            }
        }

        $views = $getViews($pdf, $tematica, $urlPdf);

        foreach ($nombres as $idx => $nombre) {
            $nombreLength = mb_strlen($nombre, 'UTF-8');
            $fontClass = $nombreLength > 20 ? 'extra-small-text-size' : ($nombreLength > 16 ? 'small-text-size' : 'normal-text-size');
            Log::info($colores);
            Log::info($imagenes);
            $plantilla = [
                'colores' => $colores,
                'imagen' => $imagenes,
                'fontClass' => $fontClass,
                'columna' => $columna,
                'filas' => 19,
            ];

            $product_order = (object)['name' => $nombre, 'firstName' => $firstNames[$idx] ?? null, 'order' => (object)['id_external' => $ventaId]];

            foreach ($views as $i => $view) {
                $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-{$tematica}-" . ($i + 1) . ".pdf";
                $renderPdf($view, $plantilla, $product_order, $filePath);
            }
        }

        return $outputFiles;
    }

    /**
     * Genera el/los PDF de etiqueta a partir de un diseño armado desde el editor
     * del front (product_pdf_designs), en vez de las vistas fijas por temática.
     * No modifica generarEtiquetas(): es el equivalente para diseños nuevos.
     */
    public static function generarEtiquetasDesdeDesign(int $ventaId, ProductPdfDesign $design, $productOrder, array $nombres, $customColor, $customIcon, $fechaCompra = null, array $firstNames = []): array
    {
        $outputFiles = [];
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
        $dirPath = storage_path("app/pdf/planchas/{$fechaCarpeta}");
        if (!is_dir($dirPath)) mkdir($dirPath, 0755, true);

        $pages = self::normalizarPaginasDesign($design->data ?? []);
        $sufijo = self::limpiarNombreArchivo(strtoupper($design->name ?: 'DESIGN'));

        foreach ($nombres as $idx => $nombre) {
            $firstName = $firstNames[$idx] ?? null;

            $resolvedPages = array_map(function ($page) use ($nombre, $firstName, $customColor, $customIcon) {
                return [
                    'sheet' => $page['sheet'] ?? ['width_cm' => 18.5, 'height_cm' => 29],
                    'elements' => array_map(
                        fn($el) => self::resolverElementoDesign($el, $nombre, $firstName, $customColor, $customIcon),
                        $page['elements'] ?? []
                    ),
                ];
            }, $pages);

            // dompdf fija el tamaño físico del PDF una sola vez para todo el
            // documento: hojas con sheet.width_cm/height_cm distintos no pueden
            // convivir en el mismo archivo sin que unas se corten o queden con
            // márgenes de sobra. Se agrupan por tamaño, se renderiza un PDF
            // temporal por grupo (cada uno con el tamaño de página exacto de
            // esa hoja) y se fusionan con FPDI en el único archivo final,
            // igual que ya hace app/Console/Commands/GenerarEtiquetas.php.
            $grupos = self::agruparPaginasPorTamano($resolvedPages);

            $product_order = (object)[
                'name' => $nombre,
                'firstName' => $firstNames[$idx] ?? null,
                'order' => (object)['id_external' => $ventaId],
            ];

            $filePath = "{$dirPath}/{$ventaId}-{$productOrder->id}-{$productOrder->product->name}-{$sufijo}-" . ($idx + 1) . ".pdf";
            $tmpFiles = [];

            try {
                foreach ($grupos as $grupoIdx => $grupo) {
                    $plantilla = [
                        'design' => [
                            'pages' => $grupo['pages'],
                        ],
                    ];

                    $widthPt = $grupo['width_cm'] * self::CM_TO_PT;
                    $heightPt = $grupo['height_cm'] * self::CM_TO_PT;

                    $pdf = Pdf::loadView('tematica.editor.RENDER', compact('plantilla', 'product_order'))
                        ->setPaper([0, 0, $widthPt, $heightPt]);
                    $dompdf = $pdf->getDomPDF();
                    $dompdf->getOptions()->setFontDir(public_path('fonts'));
                    $dompdf->getOptions()->setFontCache(storage_path('fonts_cache'));

                    $tmpPath = "{$filePath}.tmp{$grupoIdx}.pdf";
                    $pdf->save($tmpPath);
                    $tmpFiles[] = $tmpPath;
                }

                if (count($tmpFiles) === 1) {
                    rename($tmpFiles[0], $filePath);
                } else {
                    $fpdi = new Fpdi();
                    foreach ($tmpFiles as $tmpFile) {
                        $pageCount = $fpdi->setSourceFile($tmpFile);
                        for ($page = 1; $page <= $pageCount; $page++) {
                            $tplId = $fpdi->importPage($page);
                            $size = $fpdi->getTemplateSize($tplId);
                            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                            $fpdi->useTemplate($tplId);
                        }
                    }
                    $fpdi->Output($filePath, 'F');
                    foreach ($tmpFiles as $tmpFile) {
                        @unlink($tmpFile);
                    }
                }

                $outputFiles[] = $filePath;
                Log::info("✅ PDF (editor) generado", ['path' => $filePath]);
            } catch (\Throwable $e) {
                foreach ($tmpFiles as $tmpFile) {
                    @unlink($tmpFile);
                }
                Log::error("❌ Error generando PDF desde diseño del editor", [
                    'design_id' => $design->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $outputFiles;
    }

    /**
     * Agrupa páginas ya resueltas por tamaño físico de hoja (width_cm/height_cm),
     * conservando el orden de aparición tanto de los grupos como de las páginas
     * dentro de cada uno.
     */
    private static function agruparPaginasPorTamano(array $resolvedPages): array
    {
        $grupos = [];

        foreach ($resolvedPages as $page) {
            $widthCm = (float) ($page['sheet']['width_cm'] ?? 18.5);
            $heightCm = (float) ($page['sheet']['height_cm'] ?? 29);
            $key = round($widthCm, 2) . 'x' . round($heightCm, 2);

            if (!isset($grupos[$key])) {
                $grupos[$key] = ['width_cm' => $widthCm, 'height_cm' => $heightCm, 'pages' => []];
            }

            $grupos[$key]['pages'][] = $page;
        }

        return array_values($grupos);
    }

    /**
     * Un diseño puede tener varias páginas (data.pages[]), cada una con su
     * propia hoja y elementos — se renderizan como páginas del mismo PDF.
     * Si un diseño viejo todavía tiene el formato de una sola página
     * (data.sheet/data.elements sueltos), se normaliza a pages[] igual.
     */
    private static function normalizarPaginasDesign(array $data): array
    {
        if (!empty($data['pages']) && is_array($data['pages'])) {
            return $data['pages'];
        }

        if (!empty($data['elements'])) {
            return [[
                'sheet' => $data['sheet'] ?? ['width_cm' => 18.5, 'height_cm' => 29],
                'elements' => $data['elements'],
            ]];
        }

        return [];
    }

    /**
     * Resuelve un elemento del JSON del diseño: icono/tipografía reales desde los
     * catálogos existentes, texto con el nombre del cliente, y overrides del cliente
     * (color/ícono) SOLO si el elemento fue marcado como editable por el admin.
     */
    private static function resolverElementoDesign(array $el, string $nombre, ?string $firstName, $customColor, $customIcon): array
    {
        $type = $el['type'] ?? null;
        $editable = ($el['editable_by_customer'] ?? false) === true;
        $field = $el['editable_field'] ?? null;

        if ($type === 'icon') {
            $iconPath = null;

            if ($editable && $field === 'icon' && $customIcon) {
                $iconPath = public_path($customIcon);
            } elseif (!empty($el['icon_id'])) {
                $icon = PersonalizationIcon::find($el['icon_id']);
                $iconPath = $icon && $icon->icon ? public_path($icon->icon) : null;
            }

            $el['resolved_icon_path'] = $iconPath;
        }

        if ($type === 'background' && !empty($el['label_shape_id'])) {
            $shape = LabelShape::find($el['label_shape_id']);
            if ($shape) {
                $el['resolved_shape_type'] = $shape->shape_type;
                $el['resolved_shape_corner_radius_cm'] = $shape->data['corner_radius_cm'] ?? 0;
            }
        }

        if ($type === 'text') {
            $content = $el['content'] ?? '{{customer_name}}';
            $isCustomerName = str_contains($content, '{{customer_name}}');
            $el['is_customer_name'] = $isCustomerName;
            $el['resolved_text'] = str_replace('{{customer_name}}', $nombre, $content);

            $el['resolved_font_family'] = null;
            $el['resolved_font_files'] = [];

            if (!empty($el['font_id'])) {
                $typography = Typography::with('files')->find($el['font_id']);
                if ($typography) {
                    $el['resolved_font_family'] = $typography->name;
                    $el['resolved_font_files'] = $typography->files
                        ->map(fn($file) => public_path($file->file_path))
                        ->values()
                        ->all();
                }
            }

            $el['resolved_text_html'] = self::formatearTextoElemento(
                $el['resolved_text'],
                $el,
                $isCustomerName ? $firstName : null
            );
            $el['resolved_font_size_px'] = self::resolverTamanoFuente($el);
        }

        if (in_array($type, ['background', 'text'], true) && $editable && $field === 'color' && $customColor) {
            $colorValue = is_array($customColor) ? ($customColor[0] ?? null) : $customColor;
            if ($colorValue) {
                $el['color'] = ['mode' => 'hex', 'value' => $colorValue];
            }
        }

        return $el;
    }

    /**
     * Arma el texto final (con los <br> de corte de renglón) para un elemento
     * de texto del editor, usando formatName() con los límites que haya
     * configurado el admin en ese elemento (o los defaults de siempre: 3
     * renglones máx, 10 caracteres por renglón). Si min_lines pide más
     * renglones de los que formatName generó, rellena con renglones vacíos.
     */
    private static function formatearTextoElemento(string $texto, array $el, ?string $firstName): string
    {
        $maxLines = (int) ($el['max_lines'] ?? 3);
        $maxCharsPerLine = (int) ($el['max_chars_per_line'] ?? 10);
        $minLines = (int) ($el['min_lines'] ?? 1);

        $formateado = formatName($texto, $maxLines, $maxCharsPerLine, $firstName);

        $lineas = explode('<br>', $formateado);
        while (count($lineas) < $minLines) {
            $lineas[] = '&nbsp;';
        }

        return implode('<br>', $lineas);
    }

    /**
     * Tamaño de fuente configurable según cantidad de caracteres: font_size_rules
     * es una lista de { max_chars, font_size_px } — se usa la primera regla cuyo
     * max_chars sea mayor o igual a la longitud del texto (max_chars null/ausente
     * = sin límite, sirve de regla "para el resto"). Si no hay reglas o ninguna
     * matchea, se usa el font_size_px fijo del elemento.
     */
    private static function resolverTamanoFuente(array $el): ?int
    {
        $largo = mb_strlen($el['resolved_text'] ?? '', 'UTF-8');
        $reglas = $el['font_size_rules'] ?? null;

        if (!empty($reglas) && is_array($reglas)) {
            $reglasOrdenadas = $reglas;
            usort($reglasOrdenadas, function ($a, $b) {
                $maxA = $a['max_chars'] ?? PHP_INT_MAX;
                $maxB = $b['max_chars'] ?? PHP_INT_MAX;
                return $maxA <=> $maxB;
            });

            foreach ($reglasOrdenadas as $regla) {
                $maxChars = $regla['max_chars'] ?? null;
                if (($maxChars === null || $largo <= $maxChars) && isset($regla['font_size_px'])) {
                    return (int) $regla['font_size_px'];
                }
            }
        }

        return isset($el['font_size_px']) ? (int) $el['font_size_px'] : null;
    }

    /**
     * 🔠 Devuelve el tamaño de fuente según la cantidad de caracteres y la variación.
     */
    private static function getFontSize(string $name, string $typography = ''): string
    {
        $cantCharsName = mb_strlen($name, 'UTF-8');

        if (strtoupper($typography) === 'BOLD') {
            $fontSize = '78px';
            if ($cantCharsName > 5) $fontSize = '70px';
            if ($cantCharsName > 6) $fontSize = '46px';
            if ($cantCharsName > 9) $fontSize = '36px';
            if ($cantCharsName > 11) $fontSize = '32px';
        } else {
            $fontSize = '90px';
            if ($cantCharsName > 5) $fontSize = '82px';
            if ($cantCharsName > 6) $fontSize = '68px';
            if ($cantCharsName > 9) $fontSize = '52px';
            if ($cantCharsName > 11) $fontSize = '46px';
        }

        return $fontSize;
    }

    /**
     * 🧹 Limpia un nombre para uso en rutas de archivos.
     * Remueve acentos, caracteres especiales y espacios múltiples.
     */
    private static function limpiarNombreArchivo(string $nombre): string
    {
        // Tabla de reemplazo de caracteres acentuados
        $acentos = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'Ñ' => 'N', 'ñ' => 'n', 'Ç' => 'C', 'ç' => 'c'
        ];

        // Reemplazar acentos
        $limpio = strtr($nombre, $acentos);

        // Remover caracteres especiales excepto letras, números, espacios y guiones
        $limpio = preg_replace('/[^A-Za-z0-9\s\-]/', '', $limpio);

        // Reemplazar espacios múltiples por uno solo
        $limpio = preg_replace('/\s+/', ' ', $limpio);

        // Trim espacios al inicio y final
        $limpio = trim($limpio);

        return $limpio;
    }
}
