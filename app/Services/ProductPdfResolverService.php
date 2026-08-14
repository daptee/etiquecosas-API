<?php

namespace App\Services;

use App\Models\ProductPdf;
use App\Models\ProductPdfDesign;
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
        $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

        $design = ProductPdfDesign::where('product_id', $productOrder->product_id)
            ->where('is_published', true)
            ->when($variant, fn($q) => $q->where('theme_key', $tematicaId))
            ->when(!$variant, fn($q) => $q->whereNull('theme_key'))
            ->first();

        if ($design) {
            try {
                $paths = EtiquetaService::generarEtiquetasDesdeDesign(
                    $ventaId,
                    $design,
                    $productOrder,
                    [$nombreCompleto],
                    $customColor,
                    $customIcon,
                    $fecha,
                    [$form['name'] ?? '']
                );
                Log::info("PDF generado desde diseño del editor para {$nombreCompleto}, design ID: {$design->id}");
                return $paths;
            } catch (\Throwable $e) {
                Log::error("Error generando PDF desde diseño del editor para {$nombreCompleto}, design ID: {$design->id}", [
                    'error' => $e->getMessage(),
                    'product_order_id' => $productOrder->id,
                ]);
                return [];
            }
        }

        return self::resolveLegacy($ventaId, $productOrder, $variant, $nombreCompleto, $form, $customColor, $customIcon, $fecha);
    }

    /**
     * Flujo legacy sin modificar: extraído tal cual estaba duplicado en cada call site.
     */
    private static function resolveLegacy(
        int $ventaId,
        $productOrder,
        $variant,
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

            if ($variant) {
                $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                if (!$tematicaId) {
                    Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    return $pdfPaths;
                }

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
