<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\CintaCoserService;
use App\Services\CintaPlancharService;
use App\Services\EtiquetaService;
use App\Services\ProductPdfResolverService;
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

                ProductPdfResolverService::resolveAndGenerate(
                    $sale->id,
                    $productOrder,
                    $nombreCompleto,
                    $form,
                    $customColor,
                    $customIcon,
                    $sale->created_at
                );

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
