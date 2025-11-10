<?php

namespace App\Services;

use App\Models\Sale;
use Log;

class StockService
{
    /**
     * Descuenta stock de una venta aprobada o confirmada.
     */
    public static function discountStock(Sale $sale): void
    {
        foreach ($sale->products as $productOrder) {
            $product = $productOrder->product;
            $variant = $productOrder->variant;
            $quantity = $productOrder->quantity;
            $channelId = $sale->channel_id;

            // Si tiene variante
            if ($variant) {
                $stockChannels = $variant->stock_channels ?? [];

                foreach ($stockChannels as &$channel) {
                    if ($channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = max(0, ($channel['stock_quantity'] ?? 0) - $quantity);
                    }
                }

                $variant->stock_channels = $stockChannels;
                $variant->save();
            } else {
                // Producto sin variante
                $stockChannels = $product->stock_channels ?? [];

                foreach ($stockChannels as &$channel) {
                    if ($channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = max(0, ($channel['stock_quantity'] ?? 0) - $quantity);
                    }
                }

                $product->stock_channels = $stockChannels;
                $product->save();
            }
        }
    }

    /**
     * Restaura el stock de una venta (revierte lo descontado).
     */
    public static function restoreStock(Sale $sale): void
    {
        foreach ($sale->products as $productOrder) {
            $product = $productOrder->product;
            $variant = $productOrder->variant;
            $quantity = $productOrder->quantity;
            $channelId = $sale->channel_id;

            if ($variant) {
                $stockChannels = $variant->stock_channels ?? [];

                foreach ($stockChannels as &$channel) {
                    if ($channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = ($channel['stock_quantity'] ?? 0) + $quantity;
                    }
                }

                $variant->stock_channels = $stockChannels;
                $variant->save();
            } else {
                $stockChannels = $product->stock_channels ?? [];

                foreach ($stockChannels as &$channel) {
                    if ($channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = ($channel['stock_quantity'] ?? 0) + $quantity;
                    }
                }
                Log::info("stockkk");
                Log::info($stockChannels);

                $product->stock_channels = $stockChannels;
                $product->save();
            }
        }
    }
}
