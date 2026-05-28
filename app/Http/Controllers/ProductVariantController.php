<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ProductVariantController extends Controller
{
    use ApiResponse, Auditable;

    /**
     * POST /products/{id}/variants/bulk-delete
     * Elimina (soft delete) las variantes indicadas del producto.
     */
    public function bulkDelete(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'variant_ids'   => 'required|array|min:1',
            'variant_ids.*' => 'required|integer|exists:product_variants,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $variants = ProductVariant::where('product_id', $product->id)
            ->whereIn('id', $request->variant_ids)
            ->get();

        $deleted = 0;
        foreach ($variants as $variant) {
            $variant->delete();
            $deleted++;
        }

        $this->logAudit(Auth::user(), 'Bulk Delete Product Variants', $request->all(), ['deleted_count' => $deleted]);

        return $this->success(
            ['processed' => $deleted],
            "{$deleted} variantes eliminadas correctamente"
        );
    }

    /**
     * POST /products/{id}/variants/bulk-update-price
     * Modifica el precio (y opcionalmente el precio oferta) de las variantes indicadas.
     *
     * Body:
     *   variant_ids: int[]
     *   type: "percentage" | "fixed"
     *   operation: "increase" | "decrease"
     *   value: float (porcentaje o monto fijo)
     *   apply_to_discounted: bool (default false)
     */
    public function bulkUpdatePrice(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'variant_ids'          => 'required|array|min:1',
            'variant_ids.*'        => 'required|integer|exists:product_variants,id',
            'type'                 => 'required|in:percentage,fixed',
            'operation'            => 'required|in:increase,decrease',
            'value'                => 'required|numeric|min:0',
            'apply_to_discounted'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $type               = $request->input('type');
        $operation          = $request->input('operation');
        $value              = (float) $request->input('value');
        $applyToDiscounted  = (bool) $request->input('apply_to_discounted', false);

        $variants = ProductVariant::where('product_id', $product->id)
            ->whereIn('id', $request->variant_ids)
            ->get();

        $processed = 0;
        foreach ($variants as $variant) {
            $variantData = $variant->variant;

            $variantData['price'] = $this->applyPriceChange(
                (float) ($variantData['price'] ?? 0),
                $type,
                $operation,
                $value
            );

            if ($applyToDiscounted && isset($variantData['discounted_price']) && $variantData['discounted_price'] !== null) {
                $variantData['discounted_price'] = $this->applyPriceChange(
                    (float) $variantData['discounted_price'],
                    $type,
                    $operation,
                    $value
                );
            }

            $variant->variant = $variantData;
            $variant->save();
            $processed++;
        }

        $this->logAudit(Auth::user(), 'Bulk Update Price Product Variants', $request->all(), ['processed' => $processed]);

        return $this->success(
            ['processed' => $processed],
            "{$processed} variantes actualizadas correctamente"
        );
    }

    /**
     * POST /products/{id}/variants/bulk-update-stock
     * Registra movimientos de stock y/o actualiza la alerta de stock
     * para las variantes indicadas que tienen gestión de stock propia.
     *
     * Body:
     *   variant_ids: int[]
     *   quantity: int|null  (positivo=ingreso, negativo=salida; requerido si se quiere registrar movimiento)
     *   note: string|null   (requerido si se envía quantity)
     *   channel_id: int|null (null = todos los canales)
     *   stock_alert: int|null (si se provee, actualiza la alerta)
     */
    public function bulkUpdateStock(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'variant_ids'   => 'required|array|min:1',
            'variant_ids.*' => 'required|integer|exists:product_variants,id',
            'quantity'      => 'nullable|integer|not_in:0',
            'note'          => 'required_with:quantity|nullable|string|max:500',
            'channel_id'    => 'nullable|integer|exists:channels,id',
            'stock_alert'   => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $quantity   = $request->input('quantity') !== null ? (int) $request->input('quantity') : null;
        $note       = $request->input('note');
        $channelId  = $request->input('channel_id');
        $stockAlert = $request->input('stock_alert') !== null ? (int) $request->input('stock_alert') : null;

        $variants = ProductVariant::where('product_id', $product->id)
            ->whereIn('id', $request->variant_ids)
            ->get();

        $processed = 0;
        $skipped   = 0;

        foreach ($variants as $variant) {
            $stockChannels = $variant->stock_channels;

            // Solo procesar variantes con stock_channels propio (gestión de stock)
            if (empty($stockChannels)) {
                $skipped++;
                continue;
            }

            // Registrar movimiento de stock si se envió quantity
            if ($quantity !== null) {
                $updated = false;

                foreach ($stockChannels as &$channel) {
                    if ($channelId === null || $channel['channel'] == $channelId) {
                        $channel['stock_quantity'] = max(0, ($channel['stock_quantity'] ?? 0) + $quantity);
                        $updated = true;
                        if ($channelId !== null) break;
                    }
                }
                unset($channel);

                if (!$updated) {
                    $skipped++;
                    continue;
                }

                $variant->stock_channels = $stockChannels;
                $variant->save();

                StockMovement::create([
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity'           => $quantity,
                    'note'               => $note,
                    'user_id'            => Auth::id(),
                    'sale_id'            => null,
                ]);
            }

            // Actualizar alerta de stock si se envió stock_alert
            if ($stockAlert !== null) {
                $variantData               = $variant->variant;
                $variantData['stock_alert'] = $stockAlert;
                $variant->variant          = $variantData;
                $variant->save();
            }

            $processed++;
        }

        $this->logAudit(Auth::user(), 'Bulk Update Stock Product Variants', $request->all(), [
            'processed' => $processed,
            'skipped'   => $skipped,
        ]);

        return $this->success(
            ['processed' => $processed, 'skipped' => $skipped],
            "{$processed} variantes procesadas" . ($skipped > 0 ? ", {$skipped} omitidas (sin gestión de stock propia)" : "")
        );
    }

    /**
     * Aplica un cambio de precio según tipo y operación.
     */
    private function applyPriceChange(float $price, string $type, string $operation, float $value): float
    {
        $delta = $type === 'percentage' ? $price * ($value / 100) : $value;
        $newPrice = $operation === 'increase' ? $price + $delta : $price - $delta;
        return max(0, round($newPrice, 2));
    }
}
