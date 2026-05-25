<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Valida que todos los productos de una venta tengan stock suficiente
     * en el canal de la venta. Retorna un array de errores (vacío = ok).
     * Solo valida productos que tienen stock_channels configurado para ese canal.
     */
    public static function validateStock(Sale $sale): array
    {
        $errors = [];

        foreach ($sale->products as $productOrder) {
            $product   = $productOrder->product;
            $variant   = $productOrder->variant;
            $quantity  = $productOrder->quantity;
            $channelId = $sale->channel_id;

            $usesVariantStock = $variant && !empty($variant->stock_channels);
            $stockChannels    = $usesVariantStock
                ? ($variant->stock_channels ?? [])
                : ($product->stock_channels ?? []);

            if (empty($stockChannels)) {
                continue; // Sin control de stock para este producto
            }

            foreach ($stockChannels as $channel) {
                if ($channel['channel'] != $channelId) {
                    continue;
                }

                $available = (int) ($channel['stock_quantity'] ?? 0);

                if ($available < $quantity) {
                    $label = $usesVariantStock
                        ? ($product->name . ' - ' . ($variant->variant['name'] ?? 'Variante #' . $variant->id))
                        : $product->name;

                    $errors[] = [
                        'product_id'         => $product->id,
                        'product_variant_id' => $usesVariantStock ? $variant->id : null,
                        'product_name'       => $label,
                        'requested'          => $quantity,
                        'available'          => $available,
                    ];
                }
                break;
            }
        }

        return $errors;
    }

    /**
     * Descuenta stock de una venta aprobada o confirmada.
     */
    public static function discountStock(Sale $sale): void
    {
        $affectedProductIds = [];

        foreach ($sale->products as $productOrder) {
            $product   = $productOrder->product;
            $variant   = $productOrder->variant;
            $quantity  = $productOrder->quantity;
            $channelId = $sale->channel_id;

            $usesVariantStock = $variant && !empty($variant->stock_channels);

            if ($usesVariantStock) {
                $stockChannels = $variant->stock_channels;

                foreach ($stockChannels as &$channel) {
                    if ($channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = max(0, ($channel['stock_quantity'] ?? 0) - $quantity);
                    }
                }

                $variant->stock_channels = $stockChannels;
                $variant->save();
            } else {
                $stockChannels = $product->stock_channels ?? [];

                foreach ($stockChannels as &$channel) {
                    if ($channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = max(0, ($channel['stock_quantity'] ?? 0) - $quantity);
                    }
                }

                $product->stock_channels = $stockChannels;
                $product->save();
            }

            self::recordMovement(
                productId: $product->id,
                variantId: $usesVariantStock ? $variant->id : null,
                quantity:  -$quantity,
                note:      "Deducción por confirmación de pedido #{$sale->id}",
                saleId:    $sale->id,
                userId:    null
            );

            $affectedProductIds[$product->id] = $product;
        }

        foreach ($affectedProductIds as $product) {
            StockAlertService::checkAndNotify($product->fresh());
        }
    }

    /**
     * Restaura el stock de una venta (revierte lo descontado).
     */
    public static function restoreStock(Sale $sale): void
    {
        foreach ($sale->products as $productOrder) {
            $product   = $productOrder->product;
            $variant   = $productOrder->variant;
            $quantity  = $productOrder->quantity;
            $channelId = $sale->channel_id;

            $usesVariantStock = $variant && !empty($variant->stock_channels);

            if ($usesVariantStock) {
                $stockChannels = $variant->stock_channels;

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

                $product->stock_channels = $stockChannels;
                $product->save();
            }

            self::recordMovement(
                productId: $product->id,
                variantId: $usesVariantStock ? $variant->id : null,
                quantity:  +$quantity,
                note:      "Restauración por cancelación de pedido #{$sale->id}",
                saleId:    $sale->id,
                userId:    null
            );
        }
    }

    /**
     * Registra un movimiento de stock. Envuelto en try/catch para que
     * un fallo de logging no interrumpa la operación de stock.
     */
    private static function recordMovement(
        int    $productId,
        ?int   $variantId,
        int    $quantity,
        string $note,
        ?int   $saleId = null,
        ?int   $userId = null
    ): void {
        try {
            StockMovement::create([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'quantity'           => $quantity,
                'note'               => $note,
                'sale_id'            => $saleId,
                'user_id'            => $userId,
            ]);
        } catch (\Exception $e) {
            Log::error('StockService: Error al registrar movimiento de stock', [
                'product_id' => $productId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
