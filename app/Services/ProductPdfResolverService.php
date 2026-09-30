<?php

namespace App\Services;

use App\Models\ProductPdf;
use App\Models\ProductPdfDesignProduct;
use Illuminate\Support\Facades\Log;

class ProductPdfResolverService
{
    /**
     * Punto único de resolución de PDF de etiqueta para un producto de una venta.
     * Antes duplicado en SaleController (approveSale/changeStatusAdmin/generarPdfSale/generateBulkPdfs),
     * MercadoPagoController::generateSalePdfs y GenerateSalePdfsJob.
     *
     * Primero busca un diseño armado desde el editor (product_pdf_designs); si no hay,
     * cae en el flujo legacy con product_pdf sin ninguna modificación de comportamiento.
     */
    public static function resolveAndGenerate(
        int $ventaId,
        $productOrder,
        string $nombreCompleto,
        array $form,
        $customColor,
        $customIcon,
        $fecha
    ): array {
        $variant = $productOrder->variant?->variant;
        // $tematicaId (attribute_values.id) es solo para el flujo LEGACY de abajo,
        // no se toca. El diseño nuevo vincula por product_variants.id
        // (product_pdf_design_products.theme_key = variant_id), que es lo que
        // manda el front al vincular temáticas — no el primer atributo de la
        // variante.
        $tematicaId = $productOrder->resolved_attributes_values->first()['id'] ?? null;
        $variantId = $productOrder->variant_id;

        // Puede haber VARIOS vínculos para el mismo producto+variante (uno por
        // cada página que compone el PDF final, pudiendo venir de diseños
        // distintos) — ver product_pdf_design_products.page_id/sort_order.
        $links = ProductPdfDesignProduct::with('design')
            ->where('product_id', $productOrder->product_id)
            ->when($variantId, fn($q) => $q->where('theme_key', $variantId))
            ->when(!$variantId, fn($q) => $q->whereNull('theme_key'))
            ->whereHas('design', fn($q) => $q->where('is_published', true)->where('status_id', 1))
            ->orderBy('sort_order')
            ->get();

        if ($links->isNotEmpty()) {
            try {
                // Caso más común (un solo vínculo a un diseño entero): se
                // mantiene byte-a-byte el llamado de siempre, sin pasar por el
                // camino de "combinar páginas" para no arriesgar ese camino ya
                // probado.
                if ($links->count() === 1 && !$links->first()->page_id) {
                    $design = $links->first()->design;
                    $paths = EtiquetaService::generarEtiquetasDesdeDesign(
                        $ventaId,
                        $design,
                        $productOrder,
                        [$nombreCompleto],
                        $customColor,
                        $customIcon,
                        $fecha,
                        [$form['name'] ?? ''],
                        [$form['lastName'] ?? '']
                    );
                    Log::info("PDF generado desde diseño del editor para {$nombreCompleto}, design ID: {$design->id}");
                    return $paths;
                }

                $pageRefs = $links->map(fn($link) => ['design' => $link->design, 'page_id' => $link->page_id])->all();
                $paths = EtiquetaService::generarEtiquetasDesdeDesignsMultiples(
                    $ventaId,
                    $pageRefs,
                    $productOrder,
                    [$nombreCompleto],
                    $customColor,
                    $customIcon,
                    $fecha,
                    [$form['name'] ?? ''],
                    [$form['lastName'] ?? '']
                );
                Log::info("PDF generado combinando " . count($pageRefs) . " página(s) de diseños del editor para {$nombreCompleto}");
                return $paths;
            } catch (\Throwable $e) {
                Log::error("Error generando PDF desde diseño(s) del editor para {$nombreCompleto}", [
                    'error' => $e->getMessage(),
                    'product_order_id' => $productOrder->id,
                ]);
                return [];
            }
        }

        return self::resolveLegacy($ventaId, $productOrder, $variant, $tematicaId, $nombreCompleto, $form, $customColor, $customIcon, $fecha);
    }

    /**
     * Flujo legacy sin modificar: extraído tal cual estaba duplicado en cada call site.
     */
    private static function resolveLegacy(
        int $ventaId,
        $productOrder,
        $variant,
        $tematicaId,
        string $nombreCompleto,
        array $form,
        $customColor,
        $customIcon,
        $fecha
    ): array {
        $pdfPaths = [];

        $productPdf = ProductPdf::where('product_id', $productOrder->product_id)->first();

        if ($productPdf) {
            Log::info($productPdf);

            $tematicasGuardadas = $productPdf['data']['tematicas'] ?? [];
            Log::info("Temáticas guardadas en ProductPdf: " . count($tematicasGuardadas));

            if ($variant && $tematicaId) {
                $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);

                if ($tematicaCoincidente) {
                    try {
                        $pdfPaths[] = EtiquetaService::generarEtiquetas(
                            $ventaId,
                            $tematicaId,
                            [$nombreCompleto],
                            $productOrder,
                            $tematicaCoincidente,
                            $customColor,
                            $customIcon,
                            $fecha,
                            [$form['name'] ?? '']
                        );

                        Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                        return $pdfPaths;
                    } catch (\Throwable $e) {
                        Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                        return $pdfPaths;
                    }
                }
            } else {
                foreach ($tematicasGuardadas as $tematica) {
                    $tematicaId = $tematica['id'] ?? null;

                    try {
                        $pdfPaths[] = EtiquetaService::generarEtiquetas(
                            $ventaId,
                            $tematicaId,
                            [$nombreCompleto],
                            $productOrder,
                            $tematica,
                            $customColor,
                            $customIcon,
                            $fecha,
                            [$form['name'] ?? '']
                        );

                        Log::info("PDF generado sin variante para {$nombreCompleto}, temática ID: {$tematicaId}");
                    } catch (\Throwable $e) {
                        Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }
            }
        }

        Log::info(message: "Sin informacion del pdf en el producto con id: $productOrder->product_id");

        return $pdfPaths;
    }
}
