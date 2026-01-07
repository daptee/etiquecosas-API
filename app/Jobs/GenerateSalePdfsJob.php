<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Models\ProductPdf;
use App\Services\CintaCoserService;
use App\Services\CintaPlancharService;
use App\Services\EtiquetaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class GenerateSalePdfsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $saleId;

    /**
     * El número de veces que se puede intentar el job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos que el job puede ejecutarse antes de timeout.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutos

    public function __construct(int $saleId)
    {
        $this->saleId = $saleId;
    }

    public function handle()
    {
        try {
            Log::info('Iniciando generación de PDFs', ['sale_id' => $this->saleId]);

            $sale = Sale::with(['products.product', 'products.variant'])->find($this->saleId);

            if (!$sale) {
                Log::error('Venta no encontrada para generar PDFs', ['sale_id' => $this->saleId]);
                return;
            }

            // Limpiar PDFs previos
            EtiquetaService::limpiarPdfsDelPedido($sale->id, $sale->created_at);
            CintaCoserService::limpiarEtiquetasDeVenta($sale->id, $sale->created_at);
            CintaPlancharService::limpiarEtiquetasDeVenta($sale->id, $sale->created_at);

            foreach ($sale->products as $productOrder) {
                $customData = json_decode($productOrder->customization_data, true);

                $form = $customData['form'] ?? [];
                $nombreCompleto = trim(($form['name'] ?? '') . ' ' . ($form['lastName'] ?? ''));

                $customColor = $customData['color']['color_code'] ?? null;
                $customIcon = $customData['icon']['icon'] ?? null;

                if ($customIcon && $customData['icon']['name'] == 'Sin dibujo') {
                    $customIcon = null;
                }

                // === CINTAS PARA COSER (Producto 1291) ===
                if (CintaCoserService::esProductoCoser($productOrder->product_id)) {
                    try {
                        CintaCoserService::agregarEtiquetaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $sale->created_at
                        );
                        Log::info("Etiqueta de cinta para coser agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando etiqueta de cinta para coser", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === CINTAS PARA PLANCHAR (Producto 1247) ===
                if (CintaPlancharService::esProductoPlanchar($productOrder->product_id)) {
                    try {
                        CintaPlancharService::agregarEtiquetaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $sale->created_at
                        );
                        Log::info("Etiqueta de cinta para planchar agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando etiqueta de cinta para planchar", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                $variant = $productOrder->variant?->variant;
                $productPdf = ProductPdf::where('product_id', $productOrder->product_id)->first();

                // === Si hay un ProductPdf configurado ===
                if ($productPdf) {
                    Log::info($productPdf);

                    $tematicasGuardadas = $productPdf['data']['tematicas'] ?? [];
                    Log::info("Temáticas guardadas en ProductPdf: " . count($tematicasGuardadas));

                    if ($variant) {
                        $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                        if (!$tematicaId) {
                            Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                            continue;
                        }

                        // Buscar la temática correspondiente
                        $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);

                        if ($tematicaCoincidente) {
                            try {
                                EtiquetaService::generarEtiquetas(
                                    $sale->id,
                                    $tematicaId,
                                    [$nombreCompleto],
                                    $productOrder,
                                    $tematicaCoincidente,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );

                                Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                                continue;
                            } catch (\Throwable $e) {
                                Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                                continue;
                            }
                        }
                    } else {
                        // Sin variant: generar PDF por cada temática guardada
                        foreach ($tematicasGuardadas as $tematica) {
                            $tematicaId = $tematica['id'] ?? null;

                            try {
                                EtiquetaService::generarEtiquetas(
                                    $sale->id,
                                    $tematicaId,
                                    [$nombreCompleto],
                                    $productOrder,
                                    $tematica,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
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

                continue;
            }

            Log::info('PDFs generados exitosamente', ['sale_id' => $sale->id]);
        } catch (\Exception $e) {
            Log::error('Error generando PDFs en Job', [
                'sale_id' => $this->saleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-lanzar la excepción para que Laravel reintente el job
            throw $e;
        }
    }

    /**
     * Manejar un fallo del job.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Job de generación de PDFs falló definitivamente', [
            'sale_id' => $this->saleId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
