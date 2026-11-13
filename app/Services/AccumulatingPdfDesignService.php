<?php

namespace App\Services;

use App\Models\LabelShape;
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
 * La página 1 de `design.data` define una o varias celdas ("etiquetas"): cada
 * elemento `background` de esa página es una etiqueta distinta, con su propio
 * tamaño/layout. El tamaño real de cada una NO sale de `sheet.width_cm/
 * height_cm` (eso es el lienzo de edición — puede quedar en A4 completo, como
 * en modo normal, posicionando las etiquetas donde sea cómodo diseñarlas)
 * sino de la FORMA DE ETIQUETA (`label_shape_id`) de CADA `background` — ver
 * resolverTamanoDeCelda(). Cada vez que entra un pedido de un producto
 * vinculado a un diseño así (mismo vínculo product_pdf_design_products de
 * siempre, sin nada hardcodeado), agregarItemAlPdf() lo suma al JSON del día
 * de ESE diseño y reconstruye el PDF consolidado tileando copias de la celda
 * que le corresponda sobre hojas de impresión estándar, agrupadas en páginas
 * separadas por `accumulation_group_attribute` si el diseño lo configuró (ej.
 * "Color banda 1"), igual que BandaService agrupa por color — pero genérico a
 * cualquier atributo o ningún atributo.
 *
 * Un pedido con cantidad > 1 se resuelve según
 * `design.accumulation_quantity_mode` (ver duplicaCeldaPorCantidad()):
 * "duplicate" (default) repite la celda N veces; "badge" deja una sola celda
 * + un cartelito "xN" al lado, como ya hace CintaCoserService/CintaPlancharService.
 *
 * Si la página tiene VARIOS `background` (varias etiquetas de tamaño/layout
 * distinto, ej. una chica y una grande), qué etiqueta usa cada pedido lo
 * decide el vínculo product_pdf_design_products.label_element_id que lo
 * generó (el id de ESE background puntual) — viaja como 'label_element_id' en
 * cada ítem (ver agregarItemAlPdf()) y generarPdfConsolidado() resuelve cada
 * grupo con su propia celda/tamaño/grilla, recortando SOLO los elementos que
 * le pertenecen a ese background (ver celdaPorBackground()) — pero todas las
 * páginas resultantes, de cualquier etiqueta, se concatenan en el MISMO PDF
 * del día (celdaDelDesign()). Así un mismo diseño puede ofrecer varias
 * etiquetas distintas y cada producto elegir la suya, sin que eso implique
 * PDFs separados.
 *
 * Limitaciones de esta primera versión (no las necesita un diseño de celda
 * simple: nombre + ícono opcional, que es todo lo que usa BandaService hoy):
 * - El nombre de cada ítem no tiene apellido/atributos dinámicos asociados
 *   (esos datos son del pedido puntual, no tienen sentido en un PDF que
 *   mezcla pedidos de ventas distintas) — el color elegido por el cliente
 *   SÍ se guarda y se aplica (ver 'color' en agregarItemAlPdf()).
 */
class AccumulatingPdfDesignService
{
    // A4 real (21 x 29.7cm) — igual que BandaService/CintaCoserService/
    // CintaPlancharService/SelloService, que imprimen con setPaper('a4', ...).
    private const SHEET_WIDTH_CM = 21.0;
    private const SHEET_HEIGHT_CM = 29.7;
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
        $customColor = null,
        ?string $labelElementId = null,
        ?string $firstName = null,
        ?string $lastName = null
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
            // Por separado de 'nombre' (que puede venir pisado por
            // $productOrder->comment) — hace falta para los elementos con
            // dynamic_field "nombre"/"apellido" (nombre o apellido solos,
            // no el nombre completo), ver EtiquetaService::resolverElementoDesign.
            'first_name' => $firstName,
            'last_name' => $lastName,
            'cantidad' => $productOrder->quantity ?? 1,
            'group_value' => self::resolverValorDeAgrupacion($design, $productOrder),
            // Hex del color de agrupación (solo cuando accumulation_group_attribute
            // es "Color") — para pintar el cartelito del grupo con ese color de
            // fondo, ver armarPagina(). null en cualquier otro atributo de
            // agrupación (ahí el cartelito sigue siendo texto negro sin fondo).
            'group_color' => self::resolverHexDeAgrupacion($design, $productOrder),
            'icono' => $customIcon,
            'color' => $customColor,
            // Qué elemento `background` (qué "etiqueta") de la página usa este
            // ítem — ver celdaDelDesign()/celdaPorBackground()/generarPdfConsolidado().
            // null = la primera etiqueta de la página (default, diseños de una sola
            // etiqueta, como siempre). Viene del vínculo producto→diseño
            // (product_pdf_design_products.label_element_id) que generó este ítem,
            // para que un mismo diseño pueda tener varias etiquetas distintas (varios
            // backgrounds en la MISMA página) y cada producto elija la suya, todas en
            // el mismo PDF.
            'label_element_id' => $labelElementId,
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
     * Hex del color elegido por el cliente (customization_data.color.color_code),
     * solo cuando el diseño agrupa por "Color" (ver resolverValorDePersonalizacion)
     * — null en cualquier otro atributo de agrupación, o si el pedido no
     * personalizó ningún color.
     */
    private static function resolverHexDeAgrupacion(ProductPdfDesign $design, $productOrder): ?string
    {
        $attributeName = $design->accumulation_group_attribute;
        $normalizado = mb_strtolower(trim((string) $attributeName), 'UTF-8');
        if ($normalizado !== 'color') {
            return null;
        }

        $customData = json_decode($productOrder->customization_data ?? '', true) ?? [];
        $hex = trim((string) ($customData['color']['color_code'] ?? ''));

        return $hex !== '' ? $hex : null;
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

    /**
     * Un diseño acumulable puede tener varias "etiquetas" distintas (varias
     * elementos `background`, cada uno su propio tamaño/layout) en la MISMA
     * página: cada ítem trae su propio `label_element_id` (ver
     * agregarItemAlPdf) según qué background eligió el vínculo
     * producto→diseño que lo generó. Acá se parte primero por
     * `label_element_id` (cada grupo resuelve su PROPIA celda/tamaño/grilla,
     * como si fuera un diseño aparte) y recién dentro de cada uno se aplica
     * agrupación por atributo + cantidad, igual que antes — pero todas las
     * páginas resultantes (de cualquier etiqueta) se concatenan en el MISMO
     * PDF del día.
     */
    private static function generarPdfConsolidado(ProductPdfDesign $design, string $fecha, array $items): void
    {
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

        $itemsPorEtiqueta = [];
        foreach ($items as $item) {
            $itemsPorEtiqueta[$item['label_element_id'] ?? ''][] = $item;
        }

        $pages = [];
        $gruposUsados = [];

        foreach ($itemsPorEtiqueta as $labelElementId => $itemsDeEstaEtiqueta) {
            $cellPage = self::celdaDelDesign($design, $labelElementId !== '' ? $labelElementId : null);
            if (!$cellPage) {
                Log::warning('AccumulatingPdfDesignService: no se encontró la etiqueta (background) para estos ítems', [
                    'design_id' => $design->id,
                    'label_element_id' => $labelElementId,
                ]);
                continue;
            }

            [$cellW, $cellH] = self::resolverTamanoDeCelda($design, $cellPage);
            [$originX, $originY] = self::resolverOrigenDeCelda($cellPage);

            $badgeW = $duplicarPorCantidad ? 0.0 : self::BADGE_WIDTH_CM;
            $slotW = $cellW + $badgeW;

            $availableW = self::SHEET_WIDTH_CM - 2 * self::MARGIN_X_CM;
            $availableH = self::SHEET_HEIGHT_CM - self::MARGIN_TOP_CM - $headerH - self::MARGIN_TOP_CM;
            $cols = (int) ($design->accumulation_cols ?: max(1, (int) floor(($availableW + $gap) / ($slotW + $gap))));
            $rows = (int) ($design->accumulation_rows ?: max(1, (int) floor(($availableH + $gap) / ($cellH + $gap))));
            $porPagina = max(1, $cols * $rows);

            // Agrupa manteniendo el orden de llegada de los pedidos.
            $grupos = [];
            foreach ($itemsDeEstaEtiqueta as $item) {
                if ($duplicarPorCantidad) {
                    $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
                    for ($i = 0; $i < $cantidad; $i++) {
                        $grupos[$item['group_value'] ?? '__sin_grupo__'][] = $item;
                    }
                } else {
                    $grupos[$item['group_value'] ?? '__sin_grupo__'][] = $item;
                }
            }

            foreach ($grupos as $groupValue => $itemsDelGrupo) {
                // Mismo color para todo el grupo (agrupados justamente por ese
                // valor) — alcanza con el del primer ítem.
                $groupColorHex = $itemsDelGrupo[0]['group_color'] ?? null;

                foreach (array_chunk($itemsDelGrupo, $porPagina) as $chunk) {
                    $pages[] = self::armarPagina(
                        $cellPage,
                        $chunk,
                        $rows,
                        $cellW,
                        $cellH,
                        $gap,
                        $headerH,
                        $hasGroups ? (string) $groupValue : null,
                        $duplicarPorCantidad ? 0.0 : $badgeW,
                        $originX,
                        $originY,
                        $groupColorHex
                    );
                }
            }

            $gruposUsados = array_merge($gruposUsados, array_keys($grupos));
        }

        if (!$pages) {
            return;
        }

        self::renderizarPaginas($pages, self::archivoPdf($design->id, $fecha));

        Log::info('PDF consolidado del diseño acumulable regenerado', [
            'design_id' => $design->id,
            'fecha' => $fecha,
            'grupos' => array_values(array_unique($gruposUsados)),
            'total_items' => count($items),
            'total_paginas' => count($pages),
        ]);
    }

    /**
     * "Celda" (etiqueta) a usar para estos ítems: la página 1 del diseño,
     * recortada a SOLO el elemento `background` elegido (y lo que esté
     * anclado/dibujado sobre él — ver celdaPorBackground()) — así un diseño
     * con varias etiquetas en la misma página (varios `background`) no mezcla
     * los elementos de una con los de otra.
     *
     * $labelElementId null (o no encontrado) = la primera etiqueta
     * (background) de la página — compat con diseños de una sola etiqueta,
     * de siempre. Si la página no tiene NINGÚN background (diseño 100% a
     * mano, previo a que existiera esta forma de armar la celda), se usa la
     * página entera tal cual (comportamiento legacy).
     */
    private static function celdaDelDesign(ProductPdfDesign $design, ?string $labelElementId = null): ?array
    {
        $pages = $design->data['pages'] ?? [];
        if (empty($pages) || !is_array($pages[0] ?? null) || empty($pages[0]['elements'])) {
            return null;
        }

        $page = $pages[0];

        if (!$labelElementId) {
            $primerBackground = self::backgroundDeCelda($page);
            $labelElementId = $primerBackground['id'] ?? null;
        }

        if (!$labelElementId) {
            // Sin ningún background en la página: no hay nada que recortar.
            return $page;
        }

        return self::celdaPorBackground($page, $labelElementId) ?? $page;
    }

    /** Primer elemento `background` de la página, si lo hay (ver celdaDelDesign/resolverTamanoDeCelda/resolverOrigenDeCelda). */
    private static function backgroundDeCelda(array $cellPage): ?array
    {
        foreach ($cellPage['elements'] as $el) {
            if (($el['type'] ?? null) === 'background') {
                return $el;
            }
        }

        return null;
    }

    /**
     * Recorta una página a UNA sola etiqueta: el elemento `background` con
     * este id, más todo lo que le pertenece —
     * 1. Los miembros de cualquier `layout_groups` cuyo `container_element_id`
     *    sea este background (ícono/texto anclados — ver EtiquetaService::
     *    aplicarLayoutGroups), y
     * 2. Cualquier otro elemento suelto (sin layout_group) cuyo centro caiga
     *    DENTRO del rectángulo de este background — ej. un texto fijo
     *    colocado a mano sobre la etiqueta, sin anclaje.
     * Otros `background` de la misma página (y lo que les pertenezca a
     * ELLOS) quedan afuera. Devuelve null si $labelElementId no es un
     * background real de esta página.
     */
    private static function celdaPorBackground(array $page, string $labelElementId): ?array
    {
        $elements = $page['elements'] ?? [];

        $indexById = [];
        foreach ($elements as $i => $el) {
            if (isset($el['id']) && (string) $el['id'] !== '') {
                $indexById[(string) $el['id']] = $i;
            }
        }

        if (!isset($indexById[$labelElementId])) {
            return null;
        }

        $background = $elements[$indexById[$labelElementId]];
        if (($background['type'] ?? null) !== 'background') {
            return null;
        }

        $miembros = [$labelElementId => true];
        $groupsDeEstaEtiqueta = [];
        foreach ($page['layout_groups'] ?? [] as $group) {
            if (($group['container_element_id'] ?? null) !== $labelElementId) {
                continue;
            }
            $groupsDeEstaEtiqueta[] = $group;
            foreach ($group['members'] ?? [] as $memberId) {
                $miembros[(string) $memberId] = true;
            }
        }

        $bx1 = (float) ($background['x_cm'] ?? 0);
        $by1 = (float) ($background['y_cm'] ?? 0);
        $bx2 = $bx1 + (float) ($background['width_cm'] ?? 0);
        $by2 = $by1 + (float) ($background['height_cm'] ?? 0);

        foreach ($elements as $el) {
            $id = (string) ($el['id'] ?? '');
            if ($id === '' || isset($miembros[$id]) || ($el['type'] ?? null) === 'background') {
                continue;
            }
            $cx = (float) ($el['x_cm'] ?? 0) + (float) ($el['width_cm'] ?? 0) / 2;
            $cy = (float) ($el['y_cm'] ?? 0) + (float) ($el['height_cm'] ?? 0) / 2;
            if ($cx >= $bx1 && $cx <= $bx2 && $cy >= $by1 && $cy <= $by2) {
                $miembros[$id] = true;
            }
        }

        $elementosDeLaEtiqueta = array_values(array_filter(
            $elements,
            fn($el) => isset($miembros[(string) ($el['id'] ?? '')])
        ));

        return [
            'id' => $page['id'] ?? null,
            'sheet' => $page['sheet'] ?? null,
            'elements' => $elementosDeLaEtiqueta,
            'layout_groups' => $groupsDeEstaEtiqueta,
        ];
    }

    /**
     * Tamaño real de UNA etiqueta — NUNCA sale de `sheet.width_cm/height_cm`
     * si hay algo mejor: `sheet` es el lienzo de edición (puede quedar en A4
     * completo, posicionando la etiqueta donde sea cómodo diseñarla, igual
     * que en modo normal), no necesariamente el tamaño físico real. Orden de
     * prioridad:
     * 1. `label_shape_id` del elemento `background` de la celda.
     * 2. `label_shape_id` de nivel-diseño (product_pdf_designs.label_shape_id).
     * 3. `width_cm`/`height_cm` propios del elemento `background` (sin forma de catálogo).
     * 4. Fallback: `sheet.width_cm/height_cm` (diseños 100% a mano, sin forma ni background).
     */
    private static function resolverTamanoDeCelda(ProductPdfDesign $design, array $cellPage): array
    {
        $background = self::backgroundDeCelda($cellPage);

        if ($background && !empty($background['label_shape_id'])) {
            $shape = LabelShape::find($background['label_shape_id']);
            if ($shape) {
                return [(float) $shape->width_cm, (float) $shape->height_cm];
            }
        }

        if ($design->label_shape_id) {
            $shape = LabelShape::find($design->label_shape_id);
            if ($shape) {
                return [(float) $shape->width_cm, (float) $shape->height_cm];
            }
        }

        if ($background) {
            return [(float) ($background['width_cm'] ?? 4.5), (float) ($background['height_cm'] ?? 3.5)];
        }

        return [
            (float) ($cellPage['sheet']['width_cm'] ?? 4.5),
            (float) ($cellPage['sheet']['height_cm'] ?? 3.5),
        ];
    }

    /**
     * Esquina superior izquierda (x_cm, y_cm) de la etiqueta tal cual quedó
     * posicionada en el lienzo de edición — todos los elementos de la celda
     * se guardan en coordenadas ABSOLUTAS de ese lienzo (ej. el background
     * puede estar en x_cm=1.17 si el diseñador lo dejó cerca del margen de
     * una hoja A4 completa), así que armarPagina() resta este origen antes
     * de sumar el offset de columna/fila — sin esto, la etiqueta quedaría
     * desplazada en cada copia tileada, no pegada al margen real de la hoja.
     * (0, 0) si no hay `background` (el resto de elementos ya se asume
     * dibujado en coordenadas relativas a la celda).
     */
    private static function resolverOrigenDeCelda(array $cellPage): array
    {
        $background = self::backgroundDeCelda($cellPage);

        return [
            (float) ($background['x_cm'] ?? 0),
            (float) ($background['y_cm'] ?? 0),
        ];
    }

    private static function armarPagina(
        array $cellPage,
        array $itemsDelGrupo,
        int $rows,
        float $cellW,
        float $cellH,
        float $gap,
        float $headerH,
        ?string $groupValue,
        float $badgeW = 0.0,
        float $originX = 0.0,
        float $originY = 0.0,
        ?string $groupColorHex = null
    ): array {
        $elements = [];

        if ($groupValue !== null) {
            $headerX = self::MARGIN_X_CM;
            $headerY = 0.15;
            $headerW = self::SHEET_WIDTH_CM - 2 * self::MARGIN_X_CM;
            $headerHBox = $headerH - 0.1;

            // Agrupando por "Color" (ver resolverHexDeAgrupacion): cartelito con
            // fondo del color real del grupo y letra blanca — mismo criterio que
            // bandas/consolidado.blade.php (.color-header), pero con el color
            // real del pedido en vez de una paleta fija por nombre. Agrupando por
            // cualquier otro atributo, el cartelito sigue siendo texto negro sin
            // fondo, como siempre.
            if ($groupColorHex !== null) {
                $elements[] = EtiquetaService::resolverElementoParaItemAcumulado([
                    'id' => 'accum-header-bg',
                    'type' => 'background',
                    'x_cm' => $headerX,
                    'y_cm' => $headerY,
                    'width_cm' => $headerW,
                    'height_cm' => $headerHBox,
                    'z_index' => 0,
                    'fill' => ['type' => 'color', 'color' => ['mode' => 'hex', 'value' => $groupColorHex]],
                    'color' => ['mode' => 'hex', 'value' => $groupColorHex],
                ], '');
            }

            $elements[] = EtiquetaService::resolverElementoParaItemAcumulado([
                'id' => 'accum-header',
                'type' => 'text',
                'x_cm' => $headerX,
                'y_cm' => $headerY,
                'width_cm' => $headerW,
                'height_cm' => $headerHBox,
                'z_index' => 1,
                'value_mode' => 'fixed',
                'content' => mb_strtoupper($groupValue, 'UTF-8'),
                'max_lines' => 1,
                'max_chars_per_line' => 80,
                // 10pt (ver bandas/consolidado.blade.php .color-header) — este
                // sistema guarda el tamaño de fuente en px CSS (96dpi): 10pt =
                // 10 * 96/72 px.
                'font_size_px' => 10 * 96 / 72,
                'font_weight' => 700,
                'text_align' => $groupColorHex !== null ? 'center' : 'left',
                'vertical_align' => 'middle',
                'color' => ['mode' => 'hex', 'value' => $groupColorHex !== null ? '#FFFFFF' : '#000000'],
            ], '');
        }

        foreach (array_values($itemsDelGrupo) as $i => $item) {
            // Por columna primero: se llena la columna 0 de arriba a abajo
            // antes de pasar a la columna 1 (al revés de un "reading order"
            // fila por fila) — así el pedido nuevo siempre cae debajo del
            // anterior, no al lado, salvo que la columna ya esté completa.
            $col = intdiv($i, $rows);
            $row = $i % $rows;
            $offsetX = self::MARGIN_X_CM + $col * ($cellW + $badgeW + $gap);
            $offsetY = self::MARGIN_TOP_CM + $headerH + $row * ($cellH + $gap);
            $nombre = mb_strtoupper($item['nombre'], 'UTF-8');
            $primerNombre = isset($item['first_name']) ? mb_strtoupper($item['first_name'], 'UTF-8') : null;
            $apellido = isset($item['last_name']) ? mb_strtoupper($item['last_name'], 'UTF-8') : null;

            $elementosCelda = EtiquetaService::resolverCeldaAcumulada($cellPage, $nombre, $item['icono'] ?? null, $item['color'] ?? null, $primerNombre, $apellido);
            foreach ($elementosCelda as $elIdx => $resuelto) {
                $resuelto['id'] = ($resuelto['id'] ?? 'el') . "-item{$i}-{$elIdx}";
                $resuelto['x_cm'] = (float) ($resuelto['x_cm'] ?? 0) - $originX + $offsetX;
                $resuelto['y_cm'] = (float) ($resuelto['y_cm'] ?? 0) - $originY + $offsetY;
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
