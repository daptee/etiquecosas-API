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
                $seenGeneral   = false;

                // Un canal por cada entrada configurada, más un chequeo general.
                // El chequeo general (channel 0) solo se agrega si la variante NO
                // gestiona stock por canal: si ya tiene stock_channels configurado,
                // el nivel general queda sin uso real (ninguna venta cae ahí) y
                // alertar sobre él genera falsos positivos (ver caso "Combo Navidad").
                $channelIds = collect($stockChannels)->pluck('channel')->map(fn($c) => (int) $c);
                if ($channelIds->isEmpty()) {
                    $channelIds = $channelIds->push(0);
                }

                foreach ($channelIds->unique() as $channelId) {
                    $stock = StockService::resolveStock($product, $variant, $channelId);
                    if ($stock === null || $stock['always_in_stock'] || $stock['stock_alert'] === null) continue;

                    // Los niveles "general" no dependen del canal: evitar alertar duplicado por cada canal heredado.
                    if ($stock['source'] === 'variant_general') {
                        if ($seenGeneral) continue;
                        $seenGeneral = true;
                    }

                    if ($stock['available'] <= $stock['stock_alert']) {
                        $channelEntry = collect($stockChannels)->firstWhere('channel', $channelId);
                        $alerts[] = [
                            'variante'     => $variantLabel,
                            'canal'        => $stock['source'] === 'variant_channel'
                                ? ($channelEntry['channel_name'] ?? ('Canal ' . $channelId))
                                : 'General',
                            'stock_actual' => $stock['available'],
                            'stock_alerta' => $stock['stock_alert'],
                        ];
                    }
                }
            }
        } else {
            $stockChannels = $product->stock_channels ?? [];
            $seenGeneral   = false;

            // Mismo criterio que arriba: el nivel general solo se chequea si el
            // producto no tiene stock por canal configurado.
            $channelIds = collect($stockChannels)->pluck('channel')->map(fn($c) => (int) $c);
            if ($channelIds->isEmpty()) {
                $channelIds = $channelIds->push(0);
            }

            foreach ($channelIds->unique() as $channelId) {
                $stock = StockService::resolveStock($product, null, $channelId);
                if ($stock === null || $stock['always_in_stock'] || $stock['stock_alert'] === null) continue;

                if ($stock['source'] === 'product_general') {
                    if ($seenGeneral) continue;
                    $seenGeneral = true;
                }

                if ($stock['available'] <= $stock['stock_alert']) {
                    $channelEntry = collect($stockChannels)->firstWhere('channel', $channelId);
                    $alerts[] = [
                        'variante'     => 'General',
                        'canal'        => $stock['source'] === 'product_channel'
                            ? ($channelEntry['channel_name'] ?? ('Canal ' . $channelId))
                            : 'General',
                        'stock_actual' => $stock['available'],
                        'stock_alerta' => $stock['stock_alert'],
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
