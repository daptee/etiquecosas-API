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
    // font_size_px del editor está en px CSS (96 dpi, igual que config/dompdf.php).
    private const PX_TO_CM = 2.54 / 96;

    // Fuente de respaldo de todos los textos del editor (y la que se usa con
    // font_id vacío): métricas compatibles con Arial, que es lo que dibuja el
    // editor. Sin esto dompdf caía en Helvetica (sin fuente) o Times (fuente
    // subida que no carga). Se declara en RENDER.blade.php.
    public const FALLBACK_FONT_FAMILY = 'Liberation Sans';
    public const FALLBACK_FONT_FILE = 'fonts/LiberationSans-Regular.ttf';

    // TEMPORAL — buffer de debug para exponer en la respuesta de la API
    // (además del log) mientras se investiga el fix de íconos por atributo.
    // Sacar junto con los Log::info('[DEBUG-ICON-FIX]...') cuando se confirme.
    public static array $debugIconLog = [];

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
    public static function generarEtiquetasDesdeDesign(int $ventaId, ProductPdfDesign $design, $productOrder, array $nombres, $customColor, $customIcon, $fechaCompra = null, array $firstNames = [], array $lastNames = []): array
    {
        $pages = self::normalizarPaginasDesign($design->data ?? []);
        $sufijo = self::limpiarNombreArchivo(strtoupper($design->name ?: 'DESIGN'));

        return self::generarEtiquetasDesdePaginas(
            $ventaId, $pages, $sufijo, "design:{$design->id}", $productOrder,
            $nombres, $customColor, $customIcon, $fechaCompra, $firstNames, $lastNames
        );
    }

    /**
     * Igual que generarEtiquetasDesdeDesign(), pero para un producto+variante
     * que arma su PDF combinando páginas puntuales de VARIOS diseños (no un
     * diseño entero) — ver product_pdf_design_products.page_id/sort_order.
     * $pageRefs: array ordenado de ['design' => ProductPdfDesign, 'page_id' => ?string].
     * page_id null = todas las páginas de ESE diseño puntual (no de los demás).
     */
    public static function generarEtiquetasDesdeDesignsMultiples(int $ventaId, array $pageRefs, $productOrder, array $nombres, $customColor, $customIcon, $fechaCompra = null, array $firstNames = [], array $lastNames = []): array
    {
        $pages = [];
        $nombresDesigns = [];

        foreach ($pageRefs as $ref) {
            /** @var ProductPdfDesign $design */
            $design = $ref['design'];
            $pageId = $ref['page_id'] ?? null;

            $paginasDelDesign = self::normalizarPaginasDesign($design->data ?? []);
            if ($pageId) {
                $paginasDelDesign = array_values(array_filter(
                    $paginasDelDesign,
                    fn($p) => ($p['id'] ?? null) === $pageId
                ));
            }

            $pages = array_merge($pages, $paginasDelDesign);
            $nombresDesigns[] = $design->name;
        }

        $sufijo = self::limpiarNombreArchivo(strtoupper(implode(' - ', array_unique($nombresDesigns)) ?: 'COMBO'));
        $logId = 'designs:' . implode(',', array_map(fn($ref) => $ref['design']->id . ($ref['page_id'] ? ":{$ref['page_id']}" : ''), $pageRefs));

        return self::generarEtiquetasDesdePaginas(
            $ventaId, $pages, $sufijo, $logId, $productOrder,
            $nombres, $customColor, $customIcon, $fechaCompra, $firstNames, $lastNames
        );
    }

    /**
     * Núcleo compartido de renderizado: recibe las páginas YA armadas (de un
     * solo diseño, o combinadas de varios) y hace el resto — resolver cada
     * elemento, agrupar por tamaño de hoja, renderizar y fusionar con FPDI.
     * $logId es solo para identificar el origen en logs/debug (no afecta el
     * render), ya que acá puede no haber un único ProductPdfDesign detrás.
     */
    private static function generarEtiquetasDesdePaginas(int $ventaId, array $pages, string $sufijo, string $logId, $productOrder, array $nombres, $customColor, $customIcon, $fechaCompra = null, array $firstNames = [], array $lastNames = []): array
    {
        $outputFiles = [];
        $fechaCarpeta = $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
        $dirPath = storage_path("app/pdf/planchas/{$fechaCarpeta}");
        if (!is_dir($dirPath)) mkdir($dirPath, 0755, true);

        $contexto = self::prepararContextoDesign($ventaId, $productOrder, $fechaCompra, $pages, $logId, $customIcon);

        foreach ($nombres as $idx => $nombre) {
            $resolvedPages = self::resolverPaginasDesign(
                $pages, $contexto, $nombre, $firstNames[$idx] ?? null, $lastNames[$idx] ?? null,
                $customColor, $customIcon, $logId
            );

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

                    // El caché de métricas de fuentes usa la ruta de
                    // config/dompdf.php (font_cache) — no se pisa acá.
                    $pdf = Pdf::loadView('tematica.editor.RENDER', compact('plantilla', 'product_order'))
                        ->setPaper([0, 0, $widthPt, $heightPt]);
                    $dompdf = $pdf->getDomPDF();
                    $dompdf->getOptions()->setFontDir(public_path('fonts'));

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
                    'log_id' => $logId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $outputFiles;
    }

    /**
     * Mismo cálculo que generarEtiquetasDesdeDesign() (resolver elementos +
     * layout_groups), pero sin renderizar: devuelve las posiciones finales
     * de cada grupo, para el GET .../preview?format=layout del editor.
     */
    public static function calcularLayoutDesdeDesign(int $ventaId, ProductPdfDesign $design, $productOrder, string $nombre, $customColor, $customIcon, $fechaCompra = null, ?string $firstName = null, ?string $lastName = null): array
    {
        $logId = "design:{$design->id}";
        $pages = self::normalizarPaginasDesign($design->data ?? []);
        $contexto = self::prepararContextoDesign($ventaId, $productOrder, $fechaCompra, $pages, $logId, $customIcon);
        $resolvedPages = self::resolverPaginasDesign($pages, $contexto, $nombre, $firstName, $lastName, $customColor, $customIcon, $logId);

        return [
            'pages' => array_map(fn($page, $index) => [
                'index' => $index,
                'id' => $page['id'],
                'groups' => $page['layout'],
            ], $resolvedPages, array_keys($resolvedPages)),
        ];
    }

    /**
     * Resuelve todas las páginas para UN nombre: cada elemento con sus datos
     * reales (resolverElementoDesign) y después los layout_groups de la
     * página, que necesitan el texto ya resuelto para medir.
     */
    private static function resolverPaginasDesign(array $pages, array $contexto, string $nombre, ?string $firstName, ?string $lastName, $customColor, $customIcon, string $logId): array
    {
        $resolvedPages = [];

        foreach ($pages as $pageIdx => $page) {
            $resolved = [
                'id' => $page['id'] ?? null,
                'sheet' => $page['sheet'] ?? ['width_cm' => 18.5, 'height_cm' => 29],
                'elements' => array_map(
                    fn($el) => self::resolverElementoDesign(
                        $el, $nombre, $firstName, $lastName, $customColor, $customIcon,
                        $contexto['attributeIcons'], $contexto['attributeFonts'],
                        $contexto['fechaTexto'], $contexto['numeroPedido'], $contexto['idProducto']
                    ),
                    $page['elements'] ?? []
                ),
            ];

            $resolvedPages[] = self::aplicarLayoutGroups($resolved, $page['layout_groups'] ?? [], $logId, $pageIdx);
        }

        return $resolvedPages;
    }

    /**
     * Datos de la venta que comparten todos los nombres/páginas: fecha,
     * número de pedido, id de producto e íconos/tipografías por atributo de
     * la variante.
     */
    private static function prepararContextoDesign(int $ventaId, $productOrder, $fechaCompra, array $pages, string $logId, $customIcon = null): array
    {
        // Para elementos de texto con dynamic_field "fecha"/"numero_pedido"
        // (o {{fecha}}/{{numero_pedido}} en content) — mismo mecanismo que
        // nombre/apellido, pero con datos de la venta en vez de datos del cliente.
        $fechaTexto = Carbon::parse($fechaCompra ?? now())->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y');
        $numeroPedido = (string) $ventaId;
        // {{id_producto}}: products.id del producto comprado. El preview arma
        // un $productOrder de prueba (stdClass) con su propio product_id.
        $idProducto = (string) ($productOrder->product_id ?? '');

        // Íconos "por atributo": productos con atributos tipo ícono (ej. "Iconos",
        // "Color banda 2") ya traen su propio ícono en el valor elegido de la
        // variante. OJO: ese ícono NO está en la columna JSON cruda
        // (variant->variant['attributesvalues'] solo tiene id/value) — se
        // resuelve recién en ProductVariant::toArray() (metadata -> icon_id ->
        // PersonalizationIcon), así que hay que pasar por ahí, no leer la
        // columna cruda directo. El elemento del diseño pide ese ícono con
        // dynamic_attribute_id, matcheando el id del ATRIBUTO (no del valor) —
        // distinto del ícono "libre" que elige el cliente en
        // customization_data.icon.
        // ?-> por sí solo no alcanza: el preview() del editor arma un
        // $productOrder de prueba (stdClass) SIN la propiedad "variant" en
        // absoluto, y leer una propiedad inexistente ya tira el warning antes
        // de llegar al "?->" (Laravel lo escala a ErrorException = 500). El
        // "??" sí lo suprime, por eso la variante se lee así primero.
        $variantModel = $productOrder->variant ?? null;
        $variantAttributesValues = collect($variantModel?->toArray()['variant']['attributesvalues'] ?? []);
        $attributeIcons = $variantAttributesValues
            ->filter(fn($av) => !empty($av['icon']) && !empty($av['attribute']['id']))
            ->keyBy(fn($av) => $av['attribute']['id'])
            ->map(fn($av) => $av['icon']);

        // Mismo mecanismo que $attributeIcons pero para tipografía: atributos
        // tipo "typography" (ej. "Tipografía") ya traen su propio archivo de
        // fuente en el valor elegido de la variante (ProductVariant::toArray()
        // resuelve "font" igual que resuelve "icon"). Un elemento de texto con
        // dynamic_attribute_id usa ESE archivo en vez de font_id.
        $attributeFonts = $variantAttributesValues
            ->filter(fn($av) => !empty($av['font']) && !empty($av['attribute']['id']))
            ->keyBy(fn($av) => $av['attribute']['id'])
            ->map(fn($av) => $av['font']);

        // TEMPORAL — sacar cuando se confirme el fix de íconos por atributo.
        $debugAttributeIcons = [
            'log_id' => $logId,
            'product_order_id' => $productOrder->id ?? null,
            'variant_id' => $variantModel->id ?? null,
            'attributeIcons' => $attributeIcons->toArray(),
            'customIcon_param' => $customIcon,
            'elementos_icon' => collect($pages)->flatMap(fn($p) => $p['elements'] ?? [])
                ->filter(fn($el) => ($el['type'] ?? null) === 'icon')
                ->map(fn($el) => ['id' => $el['id'] ?? null, 'dynamic_attribute_id' => $el['dynamic_attribute_id'] ?? null])
                ->values()->toArray(),
        ];
        Log::info('[DEBUG-ICON-FIX] attributeIcons resuelto', $debugAttributeIcons);
        self::$debugIconLog[] = ['tipo' => 'attributeIcons'] + $debugAttributeIcons;

        return [
            'fechaTexto' => $fechaTexto,
            'numeroPedido' => $numeroPedido,
            'idProducto' => $idProducto,
            'attributeIcons' => $attributeIcons,
            'attributeFonts' => $attributeFonts,
        ];
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
     * Aplica los layout_groups de una página ya resuelta: cada grupo apila sus
     * miembros (text/icon) con gap_cm fijo entre sí y centra el conjunto en la
     * caja completa de su etiqueta (el background contenedor, sin restar
     * padding). Reescribe x_cm/y_cm (y height_cm en los textos) de los
     * miembros; no toca z_index ni el orden de los elementos.
     *
     * - Miembro ausente (ícono sin imagen resuelta, texto vacío): no ocupa
     *   lugar, no suma gap y no se dibuja.
     * - Texto: alto = renglones reales × font_size × line_height, sin
     *   min_lines; se dibuja sin vertical_align (ver RENDER.blade.php).
     * - Si el conjunto no entra, no se escala: desborda y queda en el log.
     * - Grupos inválidos (datos viejos) se descartan con un warning; si un
     *   elemento está en dos grupos, se queda en el primero.
     *
     * Devuelve la página con 'layout' = posiciones finales por grupo (lo que
     * expone GET .../preview?format=layout).
     */
    private static function aplicarLayoutGroups(array $page, $groups, string $logId, int $pageIdx): array
    {
        $page['layout'] = [];
        if (empty($groups) || !is_array($groups)) {
            return $page;
        }

        $logContext = ['log_id' => $logId, 'page_index' => $pageIdx, 'page_id' => $page['id'] ?? null];
        $groups = PdfDesignSanitizer::sanitizeLayoutGroups($groups);
        $errors = PdfDesignSanitizer::validateLayoutGroups($groups, $page['elements']);

        $indexById = [];
        foreach ($page['elements'] as $i => $el) {
            if (isset($el['id']) && is_scalar($el['id']) && (string) $el['id'] !== '' && !isset($indexById[(string) $el['id']])) {
                $indexById[(string) $el['id']] = $i;
            }
        }

        foreach ($groups as $groupIdx => $group) {
            if (isset($errors[$groupIdx])) {
                Log::warning('[layout_groups] Grupo descartado', $logContext + ['group_index' => $groupIdx, 'motivo' => $errors[$groupIdx]]);
                continue;
            }
            if ($group['direction'] !== 'vertical') {
                // Fase 2 (horizontal, con medición del ancho real del texto).
                Log::warning('[layout_groups] Grupo horizontal todavía no soportado, se ignora', $logContext + ['group_id' => $group['id']]);
                continue;
            }

            $container = $page['elements'][$indexById[$group['container_element_id']]];
            $containerX = (float) ($container['x_cm'] ?? 0);
            $containerY = (float) ($container['y_cm'] ?? 0);
            $containerW = (float) ($container['width_cm'] ?? 1);
            $containerH = (float) ($container['height_cm'] ?? 1);
            $gap = (float) $group['gap_cm'];
            $align = $group['align'] ?? 'center';

            // Tamaño de cada miembro: alto en el eje principal, ancho en el transversal.
            $members = [];
            foreach ($group['members'] as $memberId) {
                $i = $indexById[$memberId];
                $el = $page['elements'][$i];
                $present = self::esMiembroLayoutPresente($el);

                if ($el['type'] === 'text') {
                    $fontSizePx = (float) ($el['resolved_font_size_px'] ?? $el['font_size_px'] ?? 32);
                    $lineHeight = (float) ($el['resolved_line_height_design'] ?? $el['line_height'] ?? 1.15);
                    $height = count($el['resolved_lines'] ?? ['']) * $fontSizePx * $lineHeight * self::PX_TO_CM;
                } else {
                    $height = (float) ($el['height_cm'] ?? 1);
                }

                $members[] = [
                    'index' => $i,
                    'present' => $present,
                    'width' => (float) ($el['width_cm'] ?? 1),
                    'height' => $height,
                ];
            }

            $presentes = array_values(array_filter($members, fn($m) => $m['present']));
            $total = array_sum(array_column($presentes, 'height')) + $gap * max(0, count($presentes) - 1);
            $blockWidth = $presentes ? max(array_column($presentes, 'width')) : 0;
            $blockX = $containerX + ($containerW - $blockWidth) / 2;
            $cursorY = $containerY + ($containerH - $total) / 2;
            $overflow = max(0, $total - $containerH);

            $debugMembers = [];
            foreach ($members as $member) {
                $el = &$page['elements'][$member['index']];

                if (!$member['present']) {
                    $el['layout_hidden'] = true;
                    $debugMembers[] = ['id' => $el['id'], 'type' => $el['type'], 'present' => false];
                    unset($el);
                    continue;
                }

                $x = match ($align) {
                    'start' => $blockX,
                    'end' => $blockX + $blockWidth - $member['width'],
                    default => $blockX + ($blockWidth - $member['width']) / 2,
                };

                $el['x_cm'] = $x;
                $el['y_cm'] = $cursorY;
                $el['rotation_deg'] = 0;
                $el['layout_group_id'] = $group['id'];

                if ($el['type'] === 'text') {
                    $el['height_cm'] = $member['height'];
                    $el['layout_text'] = true;
                    $el['resolved_text_html'] = self::renglonesAHtml($el['resolved_lines'] ?? []);
                }

                $debugMember = [
                    'id' => $el['id'],
                    'type' => $el['type'],
                    'present' => true,
                    'x_cm' => round($x, 2),
                    'y_cm' => round($cursorY, 2),
                    'width_cm' => round($member['width'], 2),
                    'height_cm' => round($member['height'], 2),
                ];
                if ($el['type'] === 'text') {
                    $debugMember['lines'] = $el['resolved_lines'] ?? [];
                }
                $debugMembers[] = $debugMember;

                $cursorY += $member['height'] + $gap;
                unset($el);
            }

            if ($overflow > 0) {
                Log::warning('[layout_groups] El grupo no entra en su etiqueta (no se escala)', $logContext + [
                    'group_id' => $group['id'],
                    'overflow_cm' => round($overflow, 2),
                ]);
            }

            $page['layout'][] = [
                'id' => $group['id'],
                'container' => [
                    'x_cm' => round($containerX, 2),
                    'y_cm' => round($containerY, 2),
                    'width_cm' => round($containerW, 2),
                    'height_cm' => round($containerH, 2),
                ],
                'overflow_cm' => round($overflow, 2),
                'members' => $debugMembers,
            ];
        }

        return $page;
    }

    /**
     * Ausente = ícono sin imagen resuelta, o texto que quedó vacío después de
     * sustituir y normalizar (ej. "apellido" sin dato).
     */
    private static function esMiembroLayoutPresente(array $el): bool
    {
        return match ($el['type'] ?? null) {
            'icon' => !empty($el['resolved_icon_path']),
            'text' => ($el['resolved_text'] ?? '') !== '',
            default => false,
        };
    }

    /**
     * Resuelve un elemento del JSON del diseño: icono/tipografía reales desde los
     * catálogos existentes, texto con el nombre del cliente, y overrides del cliente
     * (color/ícono) SOLO si el elemento fue marcado como editable por el admin.
     */
    private static function resolverElementoDesign(array $el, string $nombre, ?string $firstName, ?string $lastName, $customColor, $customIcon, $attributeIcons = null, $attributeFonts = null, ?string $fechaTexto = null, ?string $numeroPedido = null, ?string $idProducto = null): array
    {
        $type = $el['type'] ?? null;
        $editable = ($el['editable_by_customer'] ?? false) === true;
        $field = $el['editable_field'] ?? null;

        if ($type === 'icon') {
            $iconPath = null;
            $dynamicAttributeId = $el['dynamic_attribute_id'] ?? null;
            $attributeIcon = $dynamicAttributeId && $attributeIcons ? $attributeIcons->get($dynamicAttributeId) : null;

            if ($attributeIcon) {
                // Ícono propio del valor de atributo que trae la variante
                // (ej. "Iconos"/"Color banda 2") — tiene prioridad sobre el
                // ícono "libre" de customization_data cuando el elemento pide
                // uno puntual con dynamic_attribute_id.
                $iconPath = public_path($attributeIcon);
            } elseif ($editable && $field === 'icon' && $customIcon) {
                $iconPath = public_path($customIcon);
            } elseif (!empty($el['custom_icon_path'])) {
                // Imagen subida puntual para ESTE elemento (no viene del
                // catálogo personalization_icons) — mismo nivel que icon_id,
                // una alternativa a elegir un ícono del catálogo.
                $iconPath = public_path($el['custom_icon_path']);
            } elseif (!empty($el['icon_id'])) {
                $icon = PersonalizationIcon::find($el['icon_id']);
                $iconPath = $icon && $icon->icon ? public_path($icon->icon) : null;
            }

            $el['resolved_icon_path'] = $iconPath;

            // TEMPORAL — sacar cuando se confirme el fix de íconos por atributo.
            $debugElementoIcon = [
                'el_id' => $el['id'] ?? null,
                'dynamic_attribute_id' => $dynamicAttributeId,
                'attributeIcon_encontrado' => $attributeIcon,
                'customIcon_param' => $customIcon,
                'resolved_icon_path' => $iconPath,
            ];
            Log::info('[DEBUG-ICON-FIX] resolverElementoDesign icon', $debugElementoIcon);
            self::$debugIconLog[] = ['tipo' => 'resolverElementoDesign'] + $debugElementoIcon;
        }

        if ($type === 'background' && !empty($el['label_shape_id'])) {
            $shape = LabelShape::find($el['label_shape_id']);
            if ($shape) {
                $el['resolved_shape_type'] = $shape->shape_type;
                $el['resolved_shape_corner_radius_cm'] = $shape->data['corner_radius_cm'] ?? 0;
                // Formas "custom" (data.outline_svg): el path viene dibujado en
                // el propio sistema de coordenadas del label_shape (su
                // width_cm/height_cm de catálogo), no en el del elemento —
                // hace falta ese tamaño original para el viewBox del SVG.
                $el['resolved_shape_outline_svg'] = $shape->data['outline_svg'] ?? null;
                $el['resolved_shape_width_cm'] = (float) $shape->width_cm;
                $el['resolved_shape_height_cm'] = (float) $shape->height_cm;
            }
        }

        if ($type === 'text') {
            // Esquema del editor del front: value_mode "fixed" = texto literal;
            // cualquier otro valor + dynamic_field = tomar el dato real del cliente.
            $dynamicField = $el['dynamic_field'] ?? null;
            $isDynamic = ($el['value_mode'] ?? 'fixed') !== 'fixed' && $dynamicField;

            if ($isDynamic) {
                $resolvedText = match ($dynamicField) {
                    'nombre_apellido' => $nombre,
                    'apellido' => $lastName ?? '',
                    'nombre' => $firstName ?? '',
                    'fecha' => $fechaTexto ?? '',
                    'numero_pedido' => $numeroPedido ?? '',
                    default => $el['content'] ?? '',
                };
                $isCustomerName = $dynamicField === 'nombre_apellido';
            } else {
                // Esquema anterior por placeholders {{...}} dentro de content (se mantiene por compatibilidad).
                $content = $el['content'] ?? '{{customer_name}}';
                $isCustomerName = str_contains($content, '{{customer_name}}');
                $resolvedText = str_replace(
                    ['{{customer_name}}', '{{customer_first_name}}', '{{customer_last_name}}', '{{fecha}}', '{{numero_pedido}}', '{{id_producto}}'],
                    [$nombre, $firstName ?? '', $lastName ?? '', $fechaTexto ?? '', $numeroPedido ?? '', $idProducto ?? ''],
                    $content
                );
            }

            $el['is_customer_name'] = $isCustomerName;
            // Normalizado ANTES de cortar renglones y de contar caracteres para
            // las reglas por longitud, así el editor y el backend cuentan igual.
            $el['resolved_text'] = self::normalizarEspacios($resolvedText);

            $el['resolved_font_family'] = null;
            $el['resolved_font_files'] = [];

            $dynamicFontAttributeId = $el['dynamic_attribute_id'] ?? null;
            $attributeFont = $dynamicFontAttributeId && $attributeFonts ? $attributeFonts->get($dynamicFontAttributeId) : null;

            if ($attributeFont) {
                // Tipografía propia del valor de atributo que trae la variante
                // (ej. "Tipografía") — tiene prioridad sobre el font_id fijo
                // cuando el elemento pide una puntual con dynamic_attribute_id.
                // No hay un nombre de tipografía real acá (el atributo solo
                // trae la ruta del archivo) — se genera un nombre sintético,
                // solo se usa internamente para asociar el @font-face con el
                // texto, no tiene que matchear ningún catálogo.
                $el['resolved_font_family'] = 'attr-font-' . $dynamicFontAttributeId;
                $el['resolved_font_files'] = [public_path($attributeFont)];
            } elseif (!empty($el['font_id'])) {
                $typography = Typography::with('files')->find($el['font_id']);
                if ($typography) {
                    $el['resolved_font_family'] = $typography->name;
                    $el['resolved_font_files'] = $typography->files
                        ->map(fn($file) => public_path($file->file_path))
                        ->values()
                        ->all();
                }
            }

            // Renglones sin escapar (los usa aplicarLayoutGroups para medir y
            // para rearmar el HTML sin min_lines) + el HTML final ya escapado.
            $el['resolved_lines'] = self::dividirEnRenglones(
                $el['resolved_text'],
                (int) ($el['max_lines'] ?? 3),
                (int) ($el['max_chars_per_line'] ?? 10),
                $isCustomerName ? $firstName : null
            );
            $el['resolved_text_html'] = self::renglonesAHtml($el['resolved_lines'], (int) ($el['min_lines'] ?? 1));
            $el['resolved_font_size_px'] = self::resolverTamanoFuente($el);

            // dompdf calcula el line-height sin unidad en base a las métricas
            // VERTICALES de cada fuente (hhea: ascent+descent+lineGap / unitsPerEm),
            // no en base al font-size puro — confirmado con coordenadas reales:
            // el mismo "line_height: 2" da ~65% más de distancia entre renglones con
            // Oswald que con una fuente genérica, porque el hhea de Oswald vale 1.48x
            // su unitsPerEm (vs ~1.0x de una fuente "normal"). Sin esto, el mismo
            // número de line_height se ve distinto según qué tipografía se eligió.
            // Se corrige dividiendo por esa métrica, para que el número que carga el
            // admin se vea igual sin importar la fuente.
            // Sin tipografía propia el texto sale con la de respaldo (Liberation
            // Sans), así que la corrección se calcula con ESE archivo.
            $fontRatio = self::obtenerRatioMetricasFuente($el['resolved_font_files'][0] ?? public_path(self::FALLBACK_FONT_FILE));
            // line_height tal cual lo cargó el admin (ya con sus reglas): es el
            // que usa el layout para el alto del bloque (líneas × fs × lh).
            $el['resolved_line_height_design'] = self::resolverInterlineado($el);
            $el['resolved_line_height'] = $el['resolved_line_height_design'] / $fontRatio;
            $el['resolved_letter_spacing_px'] = self::resolverEspaciadoLetras($el);
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
     * HTML final de un texto del editor: los renglones escapados (un nombre
     * con "<" o "&" no puede romper el PDF) unidos con <br>. Si min_lines
     * pide más renglones de los que salieron, rellena con renglones vacíos
     * (dentro de un layout_group se llama con 1: no se reserva espacio).
     */
    private static function renglonesAHtml(array $lineas, int $minLines = 1): string
    {
        $html = array_map(fn($linea) => htmlspecialchars($linea, ENT_QUOTES, 'UTF-8'), $lineas);
        while (count($html) < $minLines) {
            $html[] = '&nbsp;';
        }

        return implode('<br>', $html);
    }

    /**
     * Cualquier secuencia de espacios en blanco (incluidos \n y \t) pasa a un
     * solo espacio, y se recortan los extremos.
     */
    private static function normalizarEspacios(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }

    /**
     * Misma lógica de corte de renglones que formatName() (nombre/apellido
     * separado en 2 líneas si se pasa $firstName, o word-wrap agrupando
     * partículas de apellidos compuestos) pero sin forzar mayúsculas — el
     * editor nuevo permite mezclar estilos ("CIRO" + "Robertito").
     * Devuelve los renglones SIN escapar; nunca más de $maxLines (lo que
     * sobra queda pegado en el último).
     */
    private static function dividirEnRenglones(string $texto, int $maxLines, int $maxCharsPerLine, ?string $firstName): array
    {
        $maxLines = max(1, $maxLines);
        $texto = self::normalizarEspacios($texto);

        if ($firstName !== null && $firstName !== '') {
            $firstNameTrim = self::normalizarEspacios($firstName);

            if (mb_strlen($texto, 'UTF-8') <= $maxCharsPerLine) {
                return [$texto];
            }

            $lastNamePart = trim(mb_substr($texto, mb_strlen($firstNameTrim, 'UTF-8'), null, 'UTF-8'));

            if ($lastNamePart !== '') {
                if ($maxLines === 1) {
                    return [$firstNameTrim . '…'];
                }
                return [$firstNameTrim, $lastNamePart];
            }
        }

        $words = explode(' ', $texto);
        $tokens = self::agruparParticulasApellido($words);

        $lines = [];
        $currentLine = '';

        foreach ($tokens as $token) {
            // Con el renglón todavía vacío se mide solo el token (antes se
            // medía " " + token: una primera palabra de maxCharsPerLine
            // caracteres o más dejaba un renglón vacío arriba).
            $candidate = $currentLine === '' ? $token : $currentLine . ' ' . $token;
            if ($currentLine !== '' && mb_strlen($candidate, 'UTF-8') > $maxCharsPerLine && count($lines) < $maxLines - 1) {
                $lines[] = trim($currentLine);
                $currentLine = $token;
            } else {
                $currentLine = $candidate;
            }
        }
        $lines[] = trim($currentLine);

        return $lines;
    }

    /**
     * Igual que groupCompoundSurnameParts() (Helpers.php) pero comparando las
     * partículas sin distinguir mayúsculas/minúsculas, preservando el texto
     * original tal cual fue tipeado.
     */
    private static function agruparParticulasApellido(array $words): array
    {
        $particles = ['DE', 'DEL', 'DE LA', 'DE LOS', 'DE LAS', 'DI', 'LA', 'LAS', 'LOS', 'EL', 'Y', 'VAN', 'VON', 'BIN', 'BTE'];
        $grouped = [];
        $i = 0;
        $total = count($words);

        while ($i < $total) {
            $matched = false;
            if ($i + 1 < $total) {
                $twoToken = mb_strtoupper($words[$i] . ' ' . $words[$i + 1], 'UTF-8');
                if (in_array($twoToken, $particles, true) && $i + 2 < $total) {
                    $grouped[] = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
                    $i += 3;
                    $matched = true;
                }
            }

            if (!$matched) {
                if (in_array(mb_strtoupper($words[$i], 'UTF-8'), $particles, true) && $i + 1 < $total) {
                    $grouped[] = $words[$i] . ' ' . $words[$i + 1];
                    $i += 2;
                } else {
                    $grouped[] = $words[$i];
                    $i++;
                }
            }
        }

        return $grouped;
    }

    /**
     * Tamaño de fuente configurable según cantidad de caracteres: font_size_rules
     * es una lista de { max_chars, font_size_px } ya resuelta por el editor
     * (incluye la regla "para el resto" con max_chars null) — se usa la
     * primera regla cuyo max_chars sea mayor o igual a la longitud del texto.
     * `length_rules`/`length_rules_enabled` son solo el estado editable del
     * editor (sin el catch-all) y NO se usan acá: cuando el toggle está
     * prendido, el editor ya manda el resultado en font_size_rules; cuando
     * está apagado, no manda font_size_rules en absoluto. Si no hay
     * font_size_rules o ninguna matchea, se usa el font_size_px fijo.
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
     * Reproduce EXACTO el cálculo que hace dompdf para la altura "natural" de
     * una fuente (vendor/dompdf/dompdf/lib/Cpdf.php::getFontHeight(), vía
     * Adapter/CPDF.php::get_font_height()):
     *
     *   (hhea.ascent - hhea.descent + hhea.lineGap) / unitsPerEm  ×  config('dompdf.options.font_height_ratio')
     *
     * El lineGap entra porque php-font-lib lo guarda como FontHeightOffset en
     * el .ufm y Cpdf::getFontHeight() lo suma (medido con Liberation Sans:
     * sin él, cada renglón salía ~3% más alto que en el navegador).
     *
     * dompdf aplica esto como factor sobre CUALQUIER line-height sin unidad
     * que se declare, así que el mismo número de line_height termina viéndose
     * distinto según la fuente — confirmado leyendo el .ufm.json cacheado de
     * Oswald (Ascender=1193, Descender=-289, igual al hhea real del archivo) y
     * reproduciendo la cuenta exacta contra coordenadas reales del PDF. Para
     * una fuente "normal" este factor ronda ~1 (ej. sans-serif genérica dio
     * ~0.99); para Oswald da ~1.63 (65% más interlineado con el mismo número).
     * Se cachea por archivo. Si no se puede leer, no corrige (1.0).
     */
    private static array $fontLineHeightRatioCache = [];

    private static function obtenerRatioMetricasFuente(?string $fontFilePath): float
    {
        if (!$fontFilePath || !file_exists($fontFilePath)) {
            return 1.0;
        }
        if (isset(self::$fontLineHeightRatioCache[$fontFilePath])) {
            return self::$fontLineHeightRatioCache[$fontFilePath];
        }

        $ratio = 1.0;
        try {
            $font = \FontLib\Font::load($fontFilePath);
            $font->parse();
            $unitsPerEm = (float) $font->getData('head', 'unitsPerEm');
            $ascent = (float) $font->getData('hhea', 'ascent');
            $descent = (float) $font->getData('hhea', 'descent');
            $lineGap = (float) ($font->getData('hhea', 'lineGap') ?? 0);
            $fontHeightRatioConfig = (float) config('dompdf.options.font_height_ratio', 1.1);

            if ($unitsPerEm > 0) {
                $ratio = (($ascent - $descent + $lineGap) / $unitsPerEm) * $fontHeightRatioConfig;
            }
        } catch (\Throwable $e) {
            $ratio = 1.0;
        }

        // Protección: si el archivo trae métricas rotas/fuera de rango
        // razonable, no corrige de más (ni divide por algo irrisorio).
        if ($ratio <= 0.1 || $ratio > 3) {
            $ratio = 1.0;
        }

        return self::$fontLineHeightRatioCache[$fontFilePath] = $ratio;
    }

    /**
     * Interlineado según la cantidad de caracteres del texto resuelto — mismo
     * esquema que resolverTamanoFuente() pero para line_height. Si no hay
     * reglas configuradas (o ninguna matchea), usa el line_height fijo del
     * elemento (o 1.15 si tampoco hay).
     */
    private static function resolverInterlineado(array $el): float
    {
        $largo = mb_strlen($el['resolved_text'] ?? '', 'UTF-8');
        $reglas = $el['line_height_rules'] ?? null;

        if (!empty($reglas) && is_array($reglas)) {
            $reglasOrdenadas = $reglas;
            usort($reglasOrdenadas, function ($a, $b) {
                $maxA = $a['max_chars'] ?? PHP_INT_MAX;
                $maxB = $b['max_chars'] ?? PHP_INT_MAX;
                return $maxA <=> $maxB;
            });

            foreach ($reglasOrdenadas as $regla) {
                $maxChars = $regla['max_chars'] ?? null;
                if (($maxChars === null || $largo <= $maxChars) && isset($regla['line_height'])) {
                    return (float) $regla['line_height'];
                }
            }
        }

        return isset($el['line_height']) ? (float) $el['line_height'] : 1.15;
    }

    /**
     * Espaciado entre letras según la cantidad de caracteres del texto
     * resuelto — mismo esquema que resolverInterlineado()/resolverTamanoFuente().
     * Si no hay reglas configuradas (o ninguna matchea), usa el
     * letter_spacing_px fijo del elemento (o 0 si tampoco hay).
     */
    private static function resolverEspaciadoLetras(array $el): float
    {
        $largo = mb_strlen($el['resolved_text'] ?? '', 'UTF-8');
        $reglas = $el['letter_spacing_rules'] ?? null;

        if (!empty($reglas) && is_array($reglas)) {
            $reglasOrdenadas = $reglas;
            usort($reglasOrdenadas, function ($a, $b) {
                $maxA = $a['max_chars'] ?? PHP_INT_MAX;
                $maxB = $b['max_chars'] ?? PHP_INT_MAX;
                return $maxA <=> $maxB;
            });

            foreach ($reglasOrdenadas as $regla) {
                $maxChars = $regla['max_chars'] ?? null;
                if (($maxChars === null || $largo <= $maxChars) && isset($regla['letter_spacing_px'])) {
                    return (float) $regla['letter_spacing_px'];
                }
            }
        }

        return isset($el['letter_spacing_px']) ? (float) $el['letter_spacing_px'] : 0.0;
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
