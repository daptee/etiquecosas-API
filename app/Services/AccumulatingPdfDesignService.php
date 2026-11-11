<?php

namespace App\Services;

use App\Models\ProductPdfDesign;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/**
 * Generaliza lo que hoy hace BandaService (un PDF por día que se va
 * completando a medida que entran pedidos) para cualquier diseño del editor
 * marcado como `fill_mode = 'accumulating'` (ver
 * alter_product_pdf_designs_add_accumulation.sql), en vez de product IDs
 * hardcodeados y una vista Blade fija.
 *
 * La página 1 de `design.data` define UNA celda (su `sheet.width_cm/height_cm`
 * es el tamaño físico de esa celda, no de la hoja completa). Cada vez que
 * entra un pedido de un producto vinculado a un diseño así (mismo vínculo
 * product_pdf_design_products de siempre, sin nada hardcodeado),
 * agregarItemAlPdf() lo suma al JSON del día de ESE diseño y reconstruye el
 * PDF consolidado tileando copias de esa celda sobre hojas de impresión
 * estándar, agrupadas en páginas separadas por `accumulation_group_attribute`
 * si el diseño lo configuró (ej. "Color banda 1"), igual que BandaService
 * agrupa por color — pero genérico a cualquier atributo o ningún atributo.
 *
 * Un pedido con cantidad > 1 se resuelve según
 * `design.accumulation_quantity_mode` (ver duplicaCeldaPorCantidad()):
 * "duplicate" (default) repite la celda N veces; "badge" deja una sola celda
 * + un cartelito "xN" al lado, como ya hace CintaCoserService/CintaPlancharService.
 *
 * Limitaciones de esta primera versión (no las necesita un diseño de celda
 * simple: nombre + ícono opcional, que es todo lo que usa BandaService hoy):
 * - Solo se usa la página 1 del diseño; el resto se ignora.
 * - El nombre de cada ítem no tiene apellido/atributos dinámicos asociados
 *   (esos datos son del pedido puntual, no tienen sentido en un PDF que
 *   mezcla pedidos de ventas distintas) — el color elegido por el cliente
 *   SÍ se guarda y se aplica (ver 'color' en agregarItemAlPdf()).
 */
class AccumulatingPdfDesignService
{
    private const SHEET_WIDTH_CM = 18.5;
    private const SHEET_HEIGHT_CM = 29.0;
    private const MARGIN_X_CM = 1.0;
    private const MARGIN_TOP_CM = 1.0;
    private const HEADER_HEIGHT_CM = 1.0;
    private const CM_TO_PT = 72 / 2.54;
    // Ancho reservado al lado de la celda para el cartelito "xN", en modo
    // accumulation_quantity_mode = "badge" (ver duplicaCeldaPorCantidad()).
    private const BADGE_WIDTH_CM = 1.0;

    /**
     * Suma (o reemplaza, si ya existía) el pedido de este productOrder al PDF
     * consolidado del día de $design, y lo regenera. No hace nada si el
     * diseño no está en modo acumulable.
     */
    public static function agregarItemAlPdf(
        ProductPdfDesign $design,
        int $ventaId,
        $productOrder,
        string $nombreCompleto,
        ?string $customIcon,
        $fechaCompra = null,
        $customColor = null
    ): void {
        if (!$design->esAcumulable()) {
            return;
        }

        $nombre = trim($productOrder->comment ?? $nombreCompleto);
        if ($nombre === '') {
            Log::warning('AccumulatingPdfDesignService: ítem sin nombre', [
                'design_id' => $design->id,
                'product_order_id' => $productOrder->id,
            ]);
            return;
        }

        $fecha = self::fechaCarpeta($fechaCompra);
        $items = self::leerItemsDelDia($design->id, $fecha);

        // Idempotente: si este product_order ya estaba (reproceso de la
        // venta — aprobación, cambio de estado, reenvío), se reemplaza en
        // vez de duplicarse.
        $items = array_values(array_filter(
            $items,
            fn($it) => ($it['product_order_id'] ?? null) !== $productOrder->id
        ));

        $items[] = [
            'venta_id' => $ventaId,
            'product_order_id' => $productOrder->id,
            'nombre' => $nombre,
            'cantidad' => $productOrder->quantity ?? 1,
            'group_value' => self::resolverValorDeAgrupacion($design, $productOrder),
            'icono' => $customIcon,
            'color' => $customColor,
        ];

        self::guardarItemsDelDia($design->id, $fecha, $items);
        self::generarPdfConsolidado($design, $fecha, $items);

        Log::info('Ítem agregado al PDF acumulable del diseño', [
            'design_id' => $design->id,
            'fecha' => $fecha,
            'nombre' => $nombre,
            'total_items_hoy' => count($items),
        ]);
    }

    /** Saca del consolidado del día todos los ítems de esta venta (ej. venta cancelada) y regenera. */
    public static function limpiarItemsDeVenta(ProductPdfDesign $design, int $ventaId, $fechaCompra = null): void
    {
        $fecha = self::fechaCarpeta($fechaCompra);
        $items = self::leerItemsDelDia($design->id, $fecha);
        $items = array_values(array_filter($items, fn($it) => ($it['venta_id'] ?? null) != $ventaId));

        if ($items) {
            self::guardarItemsDelDia($design->id, $fecha, $items);
            self::generarPdfConsolidado($design, $fecha, $items);
            return;
        }

        @unlink(self::archivoJson($design->id, $fecha));
        @unlink(self::archivoPdf($design->id, $fecha));
    }

    private static function resolverValorDeAgrupacion(ProductPdfDesign $design, $productOrder): ?string
    {
        $attributeName = $design->accumulation_group_attribute;
        if (!$attributeName) {
            return null;
        }

        // "Ícono"/"Color" no son necesariamente un atributo de variante de
        // catálogo (ver resolverValorDePersonalizacion) — se resuelven
        // primero porque no dependen de que el producto tenga variante.
        $valorPersonalizacion = self::resolverValorDePersonalizacion($attributeName, $productOrder);
        if ($valorPersonalizacion !== false) {
            return $valorPersonalizacion;
        }

        if (!$productOrder->variant) {
            return null;
        }

        foreach ($productOrder->resolved_attributes_values as $attr) {
            $attrName = $attr['attribute']['name'] ?? '';
            if (stripos($attrName, $attributeName) !== false) {
                $value = trim((string) ($attr['value'] ?? ''));
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * "Ícono"/"Color" como accumulation_group_attribute no apuntan a un
     * atributo de variante de catálogo (product_variants/attribute_values):
     * en productos como Cinta Planchar/Coser, el ícono y el color son un
     * campo de PERSONALIZACIÓN LIBRE que cada pedido completó al comprar
     * (customization_data), sin variante detrás — el mismo dato que ya
     * resuelve CintaPlancharService::agregarEtiquetaAlPdf() como
     * $customColor/$customIcon.
     *
     * Devuelve `false` (ningún valor de agrupación, ni siquiera `null`) si
     * $attributeName no es ninguno de estos dos nombres reservados, para
     * que el llamador siga con la búsqueda normal por atributo de catálogo.
     */
    private static function resolverValorDePersonalizacion(string $attributeName, $productOrder)
    {
        $normalizado = mb_strtolower(trim($attributeName), 'UTF-8');
        $esIcono = in_array($normalizado, ['icono', 'ícono', 'icon'], true);
        $esColor = $normalizado === 'color';

        if (!$esIcono && !$esColor) {
            return false;
        }

        $customData = json_decode($productOrder->customization_data ?? '', true) ?? [];

        if ($esIcono) {
            $nombre = trim((string) ($customData['icon']['name'] ?? ''));
            return $nombre !== '' ? $nombre : null;
        }

        $nombre = trim((string) ($customData['color']['name'] ?? $customData['color']['color_code'] ?? ''));
        return $nombre !== '' ? $nombre : null;
    }

    private static function generarPdfConsolidado(ProductPdfDesign $design, string $fecha, array $items): void
    {
        $cellPage = self::celdaDelDesign($design);
        if (!$cellPage) {
            Log::warning('AccumulatingPdfDesignService: el diseño no tiene una página de celda válida', ['design_id' => $design->id]);
            return;
        }

        $cellW = (float) ($cellPage['sheet']['width_cm'] ?? 4.5);
        $cellH = (float) ($cellPage['sheet']['height_cm'] ?? 3.5);
        $gap = (float) ($design->accumulation_gap_cm ?? 0.3);
        $hasGroups = !empty($design->accumulation_group_attribute);
        $headerH = $hasGroups ? self::HEADER_HEIGHT_CM : 0.0;

        // Un pedido con cantidad > 1 (ej. "x3" cintas del mismo nombre) se
        // resuelve de dos formas configurables (accumulation_quantity_mode):
        // "duplicate" (default) repite la celda esa cantidad de veces como
        // etiquetas FÍSICAS separadas; "badge" deja UNA sola celda y le suma
        // un cartelito "xN" al lado (como ya hace hoy Cintas Coser/Planchar),
        // reservando ese ancho extra en cada columna de la grilla.
        $duplicarPorCantidad = $design->duplicaCeldaPorCantidad();
        $badgeW = $duplicarPorCantidad ? 0.0 : self::BADGE_WIDTH_CM;
        $slotW = $cellW + $badgeW;

        $availableW = self::SHEET_WIDTH_CM - 2 * self::MARGIN_X_CM;
        $availableH = self::SHEET_HEIGHT_CM - self::MARGIN_TOP_CM - $headerH - self::MARGIN_TOP_CM;
        $cols = (int) ($design->accumulation_cols ?: max(1, (int) floor(($availableW + $gap) / ($slotW + $gap))));
        $rows = (int) ($design->accumulation_rows ?: max(1, (int) floor(($availableH + $gap) / ($cellH + $gap))));
        $porPagina = max(1, $cols * $rows);

        // Agrupa manteniendo el orden de llegada de los pedidos.
        $grupos = [];
        foreach ($items as $item) {
            if ($duplicarPorCantidad) {
                $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
                for ($i = 0; $i < $cantidad; $i++) {
                    $grupos[$item['group_value'] ?? '__sin_grupo__'][] = $item;
                }
            } else {
                $grupos[$item['group_value'] ?? '__sin_grupo__'][] = $item;
            }
        }

        $pages = [];
        foreach ($grupos as $groupValue => $itemsDelGrupo) {
            foreach (array_chunk($itemsDelGrupo, $porPagina) as $chunk) {
                $pages[] = self::armarPagina(
                    $cellPage,
                    $chunk,
                    $cols,
                    $cellW,
                    $cellH,
                    $gap,
                    $headerH,
                    $hasGroups ? (string) $groupValue : null,
                    $duplicarPorCantidad ? 0.0 : $badgeW
                );
            }
        }

        if (!$pages) {
            return;
        }

        self::renderizarPaginas($pages, self::archivoPdf($design->id, $fecha));

        Log::info('PDF consolidado del diseño acumulable regenerado', [
            'design_id' => $design->id,
            'fecha' => $fecha,
            'grupos' => array_keys($grupos),
            'total_items' => count($items),
            'total_paginas' => count($pages),
        ]);
    }

    /** Página 1 de data.pages, normalizada — es la celda que se repite. */
    private static function celdaDelDesign(ProductPdfDesign $design): ?array
    {
        $pages = $design->data['pages'] ?? [];
        if (empty($pages) || !is_array($pages[0] ?? null) || empty($pages[0]['elements'])) {
            return null;
        }

        return $pages[0];
    }

    private static function armarPagina(
        array $cellPage,
        array $itemsDelGrupo,
        int $cols,
        float $cellW,
        float $cellH,
        float $gap,
        float $headerH,
        ?string $groupValue,
        float $badgeW = 0.0
    ): array {
        $elements = [];

        if ($groupValue !== null) {
            $elements[] = EtiquetaService::resolverElementoParaItemAcumulado([
                'id' => 'accum-header',
                'type' => 'text',
                'x_cm' => self::MARGIN_X_CM,
                'y_cm' => 0.15,
                'width_cm' => self::SHEET_WIDTH_CM - 2 * self::MARGIN_X_CM,
                'height_cm' => $headerH - 0.1,
                'value_mode' => 'fixed',
                'content' => mb_strtoupper($groupValue, 'UTF-8'),
                'max_lines' => 1,
                'max_chars_per_line' => 80,
                'font_size_px' => 18,
                'font_weight' => 700,
                'text_align' => 'left',
                'color' => ['mode' => 'hex', 'value' => '#000000'],
            ], '');
        }

        foreach (array_values($itemsDelGrupo) as $i => $item) {
            $row = intdiv($i, $cols);
            $col = $i % $cols;
            $offsetX = self::MARGIN_X_CM + $col * ($cellW + $badgeW + $gap);
            $offsetY = self::MARGIN_TOP_CM + $headerH + $row * ($cellH + $gap);
            $nombre = mb_strtoupper($item['nombre'], 'UTF-8');

            $elementosCelda = EtiquetaService::resolverCeldaAcumulada($cellPage, $nombre, $item['icono'] ?? null, $item['color'] ?? null);
            foreach ($elementosCelda as $elIdx => $resuelto) {
                $resuelto['id'] = ($resuelto['id'] ?? 'el') . "-item{$i}-{$elIdx}";
                $resuelto['x_cm'] = (float) ($resuelto['x_cm'] ?? 0) + $offsetX;
                $resuelto['y_cm'] = (float) ($resuelto['y_cm'] ?? 0) + $offsetY;
                $elements[] = $resuelto;
            }

            $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
            if ($badgeW > 0 && $cantidad > 1) {
                $elements[] = EtiquetaService::resolverElementoParaItemAcumulado([
                    'id' => "accum-badge-item{$i}",
                    'type' => 'text',
                    'x_cm' => $offsetX + $cellW + 0.1,
                    'y_cm' => $offsetY,
                    'width_cm' => $badgeW - 0.1,
                    'height_cm' => $cellH,
                    'value_mode' => 'fixed',
                    'content' => 'x' . $cantidad,
                    'max_lines' => 1,
                    'max_chars_per_line' => 10,
                    'font_size_px' => 14,
                    'font_weight' => 400,
                    'text_align' => 'left',
                    'vertical_align' => 'middle',
                    'color' => ['mode' => 'hex', 'value' => '#333333'],
                ], '');
            }
        }

        return [
            'id' => null,
            'sheet' => ['width_cm' => self::SHEET_WIDTH_CM, 'height_cm' => self::SHEET_HEIGHT_CM],
            'cut_lines' => null,
            'elements' => $elements,
        ];
    }

    /**
     * Mismo mecanismo que EtiquetaService::generarEtiquetasDesdePaginas() para
     * convertir páginas ya resueltas en un único PDF: una hoja por página
     * (acá todas del mismo tamaño) renderizada con la vista del editor.
     */
    private static function renderizarPaginas(array $pages, string $filePath): void
    {
        $tmpFiles = [];

        try {
            foreach ($pages as $idx => $page) {
                $plantilla = ['design' => ['pages' => [$page]]];
                $product_order = (object) ['name' => '', 'firstName' => null, 'order' => (object) ['id_external' => null]];

                $widthPt = $page['sheet']['width_cm'] * self::CM_TO_PT;
                $heightPt = $page['sheet']['height_cm'] * self::CM_TO_PT;

                $pdf = Pdf::loadView('tematica.editor.RENDER', compact('plantilla', 'product_order'))
                    ->setPaper([0, 0, $widthPt, $heightPt]);
                $dompdf = $pdf->getDomPDF();
                $dompdf->getOptions()->setFontDir(public_path('fonts'));

                $tmpPath = "{$filePath}.tmp{$idx}.pdf";
                $pdf->save($tmpPath);
                $tmpFiles[] = $tmpPath;
            }

            if (count($tmpFiles) === 1) {
                rename($tmpFiles[0], $filePath);
                return;
            }

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
        } catch (\Throwable $e) {
            foreach ($tmpFiles as $tmpFile) {
                @unlink($tmpFile);
            }
            Log::error('Error renderizando PDF consolidado acumulable', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private static function fechaCarpeta($fechaCompra): string
    {
        return $fechaCompra
            ? Carbon::parse($fechaCompra)->setTimezone('America/Argentina/Buenos_Aires')->format('d-m-Y')
            : Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y');
    }

    private static function dirDelDesign(int $designId): string
    {
        return storage_path("app/pdf/disenos-acumulables/{$designId}");
    }

    private static function archivoJson(int $designId, string $fecha): string
    {
        return self::dirDelDesign($designId) . "/{$fecha}.json";
    }

    private static function archivoPdf(int $designId, string $fecha): string
    {
        return self::dirDelDesign($designId) . "/{$fecha}.pdf";
    }

    private static function leerItemsDelDia(int $designId, string $fecha): array
    {
        $jsonFile = self::archivoJson($designId, $fecha);
        if (!file_exists($jsonFile)) {
            return [];
        }

        return json_decode(file_get_contents($jsonFile), true) ?? [];
    }

    private static function guardarItemsDelDia(int $designId, string $fecha, array $items): void
    {
        $dir = self::dirDelDesign($designId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::archivoJson($designId, $fecha), json_encode($items, JSON_PRETTY_PRINT));
    }
}
