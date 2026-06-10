<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Validator;

class StockMovementController extends Controller
{
    use ApiResponse, Auditable;

    /**
     * GET /stock-movements
     * Filtros: product_id, product_variant_id, sale_id, date_from, date_to
     * Paginado, orden por created_at DESC
     */
    public function index(Request $request)
    {
        $query = StockMovement::with([
                'product:id,name,sku',
                'variant:id,product_id,variant',
                'user:id,name,email',
                'sale:id,sale_status_id',
                'channel:id,name',
            ])
            ->orderBy('created_at', 'desc');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        }

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('channel_id')) {
            $channelFilter = $request->channel_id;
            if ($channelFilter == 0 || $channelFilter === null || $channelFilter === '') {
                $query->whereNull('channel_id');
            } else {
                $query->where('channel_id', $channelFilter);
            }
        }

        $perPage = $request->query('quantity', 50);
        $movements = $query->paginate($perPage);

        return $this->success($movements, 'Movimientos de stock obtenidos correctamente');
    }

    /**
     * POST /stock-movements
     * Movimiento manual de stock (ingreso o salida) registrado por un operador.
     * Aplica la misma lógica de herencia que StockService:
     * si la variante tiene stock_channels propio → actualiza la variante,
     * de lo contrario actualiza el stock general del producto.
     */
    public function store(Request $request)
    {
        $rules = [
            'product_id'         => 'required|integer|exists:products,id',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'channel_id'         => 'nullable|integer|exists:channels,id',
            'quantity'           => 'required|integer|not_in:0',
            'note'               => 'required|string|max:500',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $product   = Product::findOrFail($request->product_id);
        $variant   = $request->product_variant_id
            ? ProductVariant::findOrFail($request->product_variant_id)
            : null;
        $channelId = $request->channel_id;
        $quantity  = (int) $request->quantity;

        $usesVariantStock = $variant && !empty($variant->stock_channels);

        if ($channelId === null) {
            // Stock general explícito: variante si no es heritable, sino producto
            if ($usesVariantStock) {
                $variantData = $variant->variant ?? [];
                $source = ($variantData['is_heritable'] ?? 0) == 1 ? 'product_general' : 'variant_general';
            } else {
                $source = 'product_general';
            }
            StockService::applyStockChange($product, $variant, 0, $quantity, $source);
        } else {
            // Validar que el canal exista en la entidad correcta
            $stockChannels = $usesVariantStock
                ? ($variant->stock_channels ?? [])
                : ($product->stock_channels ?? []);

            if (!collect($stockChannels)->firstWhere('channel', $channelId)) {
                $entity = $usesVariantStock ? 'variante' : 'producto';
                return $this->error("El canal especificado no existe en el stock de esta {$entity}", 422);
            }

            // Cascade completo via StockService
            $stock = StockService::resolveStock($product, $variant, $channelId);
            if ($stock && !$stock['always_in_stock']) {
                StockService::applyStockChange($product, $variant, $channelId, $quantity, $stock['source']);
            }
        }

        $movement = StockMovement::create([
            'product_id'         => $product->id,
            'product_variant_id' => $usesVariantStock ? $variant->id : null,
            'quantity'           => $quantity,
            'note'               => $request->note,
            'user_id'            => Auth::id(),
            'sale_id'            => null,
            'channel_id'         => $channelId,
        ]);

        $this->logAudit(Auth::user(), 'Manual Stock Movement', $request->all(), $movement);

        return $this->success(
            $movement->load(['product:id,name,sku', 'user:id,name,email']),
            'Movimiento de stock registrado correctamente',
            null,
            201
        );
    }
}
