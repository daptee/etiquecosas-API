<?php

namespace App\Services;

use App\Mail\StockAlertMail;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StockAlertService
{
    /**
     * Evalúa si algún stock está en o por debajo del umbral de alerta
     * y envía un email interno si corresponde.
     *
     * Regla: si el producto tiene variantes activas, se evalúan solo las variantes.
     *        Si no tiene variantes, se evalúa el producto a nivel general.
     */
    public static function checkAndNotify(Product $product): void
    {
        $product->loadMissing('variants');
        $activeVariants = $product->variants->filter(fn($v) => !$v->trashed());

        $alerts = [];

        if ($activeVariants->isNotEmpty()) {
            foreach ($activeVariants as $variant) {
                $variantData  = $variant->variant ?? [];
                $variantLabel = $variantData['name'] ?? ("Variante #" . $variant->id);

                $stockChannels = $variant->stock_channels ?? [];
                foreach ($stockChannels as $channel) {
                    $channelStockAlert = isset($channel['stock_alert']) ? (int) $channel['stock_alert'] : null;
                    if ($channelStockAlert === null) continue;

                    // Resolver stock real respetando is_heritable
                    $stock = StockService::resolveStock($product, $variant, (int) $channel['channel']);
                    if ($stock === null || $stock['always_in_stock']) continue;

                    if ($stock['available'] <= $channelStockAlert) {
                        $alerts[] = [
                            'variante'     => $variantLabel,
                            'canal'        => $channel['channel_name'] ?? ('Canal ' . $channel['channel']),
                            'stock_actual' => $stock['available'],
                            'stock_alerta' => $channelStockAlert,
                        ];
                    }
                }
            }
        } else {
            $stockChannels = $product->stock_channels ?? [];
            foreach ($stockChannels as $channel) {
                $channelStockAlert = isset($channel['stock_alert']) ? (int) $channel['stock_alert'] : null;
                if ($channelStockAlert === null) continue;

                // Resolver stock real respetando is_heritable
                $stock = StockService::resolveStock($product, null, (int) $channel['channel']);
                if ($stock === null || $stock['always_in_stock']) continue;

                if ($stock['available'] <= $channelStockAlert) {
                    $alerts[] = [
                        'variante'     => 'General',
                        'canal'        => $channel['channel_name'] ?? ('Canal ' . $channel['channel']),
                        'stock_actual' => $stock['available'],
                        'stock_alerta' => $channelStockAlert,
                    ];
                }
            }
        }

        if (empty($alerts)) {
            return;
        }

        try {
            Mail::to('info@etiquecosas.com.ar')->send(new StockAlertMail($product, $alerts));
            Log::info("StockAlertService: Alerta de stock enviada para producto #{$product->id} ({$product->name}). Alertas: " . count($alerts));
        } catch (\Exception $e) {
            Log::error("StockAlertService: Error al enviar alerta de stock para producto #{$product->id}: {$e->getMessage()}");
        }
    }
}
