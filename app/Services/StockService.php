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

            $stock = self::resolveStock($product, $variant, $channelId);

            if ($stock === null || $stock['always_in_stock']) {
                continue;
            }

            if ($stock['available'] < $quantity) {
                $label = $variant
                    ? ($product->name . ' - ' . ($variant->variant['name'] ?? 'Variante #' . $variant->id))
                    : $product->name;

                $errors[] = [
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'product_name'       => $label,
                    'requested'          => $quantity,
                    'available'          => $stock['available'],
                ];
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

            $stock = self::resolveStock($product, $variant, $channelId);
            if ($stock !== null && !$stock['always_in_stock']) {
                self::applyStockChange($product, $variant, $channelId, -$quantity, $stock['source']);
            }

            self::recordMovement(
                productId:  $product->id,
                variantId:  $variant ? $variant->id : null,
                quantity:   -$quantity,
                note:       "Deducción por confirmación de pedido #{$sale->id}",
                saleId:     $sale->id,
                userId:     null,
                channelId:  $channelId
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

            $stock = self::resolveStock($product, $variant, $channelId);
            if ($stock !== null && !$stock['always_in_stock']) {
                self::applyStockChange($product, $variant, $channelId, +$quantity, $stock['source']);
            }

            self::recordMovement(
                productId:  $product->id,
                variantId:  $variant ? $variant->id : null,
                quantity:   +$quantity,
                note:       "Restauración por cancelación de pedido #{$sale->id}",
                saleId:     $sale->id,
                userId:     null,
                channelId:  $channelId
            );
        }
    }

    /**
     * Jerarquía de fuente de stock para una línea de venta:
     * 1. Canal de variante  2. General de variante  3. Canal de producto  4. General de producto
     *
     * Retorna null si no hay control de stock, o un array con:
     *   - always_in_stock (bool)
     *   - available (int)
     *   - source (string): variant_channel | variant_general | product_channel | product_general
     */
    public static function resolveStock($product, $variant, int $channelId): ?array
    {
        // 1. Canal de variante — si is_heritable: 1 cae al paso 2
        if ($variant && !empty($variant->stock_channels)) {
            $ch = collect($variant->stock_channels)->firstWhere('channel', $channelId);
            if ($ch) {
                if (($ch['stock_status'] ?? null) == 1) {
                    return ['always_in_stock' => true];
                }
                if (($ch['is_heritable'] ?? 0) != 1) {
                    return [
                        'always_in_stock' => false,
                        'available'       => (int) ($ch['stock_quantity'] ?? 0),
                        'source'          => 'variant_channel',
                    ];
                }
            }
        }

        // 2. General de variante — si is_heritable: 1 cae al paso 3
        if ($variant) {
            $variantData = $variant->variant ?? [];
            if (isset($variantData['stock_quantity']) && $variantData['stock_quantity'] !== null) {
                if (($variantData['is_heritable'] ?? 0) != 1) {
                    return [
                        'always_in_stock' => false,
                        'available'       => (int) $variantData['stock_quantity'],
                        'source'          => 'variant_general',
                    ];
                }
            }
        }

        // 3. Canal de producto — si is_heritable: 1 cae al paso 4
        if (!empty($product->stock_channels)) {
            $ch = collect($product->stock_channels)->firstWhere('channel', $channelId);
            if ($ch) {
                if (($ch['stock_status'] ?? null) == 1) {
                    return ['always_in_stock' => true];
                }
                if (($ch['is_heritable'] ?? 0) != 1) {
                    return [
                        'always_in_stock' => false,
                        'available'       => (int) ($ch['stock_quantity'] ?? 0),
                        'source'          => 'product_channel',
                    ];
                }
            }
        }

        // 4. General de producto (piso de la jerarquía)
        if ($product->stock_quantity !== null) {
            return [
                'always_in_stock' => false,
                'available'       => (int) $product->stock_quantity,
                'source'          => 'product_general',
            ];
        }

        return null; // Sin control de stock
    }

    /**
     * Aplica un delta (positivo = ingreso, negativo = egreso) a la fuente correcta.
     */
    public static function applyStockChange($product, $variant, int $channelId, int $delta, string $source): void
    {
        switch ($source) {
            case 'variant_channel':
                $stockChannels = $variant->stock_channels;
                foreach ($stockChannels as &$ch) {
                    if ($ch['channel'] == $channelId) {
                        $ch['stock_quantity'] = max(0, ($ch['stock_quantity'] ?? 0) + $delta);
                        break;
                    }
                }
                unset($ch);
                $variant->stock_channels = $stockChannels;
                $variant->save();
                break;

            case 'variant_general':
                $variantData = $variant->variant ?? [];
                $variantData['stock_quantity'] = max(0, ($variantData['stock_quantity'] ?? 0) + $delta);
                $variant->variant = $variantData;
                $variant->save();
                break;

            case 'product_channel':
                $stockChannels = $product->stock_channels ?? [];
                foreach ($stockChannels as &$ch) {
                    if ($ch['channel'] == $channelId) {
                        $ch['stock_quantity'] = max(0, ($ch['stock_quantity'] ?? 0) + $delta);
                        break;
                    }
                }
                unset($ch);
                $product->stock_channels = $stockChannels;
                $product->save();
                break;

            case 'product_general':
                $product->stock_quantity = max(0, ($product->stock_quantity ?? 0) + $delta);
                $product->save();
                break;
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
        ?int   $userId = null,
        ?int   $channelId = null
    ): void {
        try {
            StockMovement::create([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'quantity'           => $quantity,
                'note'               => $note,
                'sale_id'            => $saleId,
                'user_id'            => $userId,
                'channel_id'         => $channelId,
            ]);
        } catch (\Exception $e) {
            Log::error('StockService: Error al registrar movimiento de stock', [
                'product_id' => $productId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
