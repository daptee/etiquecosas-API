<?php

namespace App\Http\Controllers;

use App\Models\AbandonedCartLog;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbandonedCartLogController extends Controller
{
    use ApiResponse;

    // 📌 Detalle público del carrito para el link "Ir a mi carrito" de los mails (usado por el front)
    public function showByUid($uid)
    {
        $log = AbandonedCartLog::where('uid', $uid)->first();

        if (!$log) {
            return $this->error('Carrito no encontrado', 404);
        }

        $sale = $log->sale;

        if (!$sale) {
            return $this->error('Carrito no encontrado', 404);
        }

        if ($sale->sale_status_id != 8) {
            return $this->error('Este carrito ya no está disponible', 410);
        }

        $sale->load(['products.product.images', 'products.variant', 'shippingMethod']);

        $data = [
            'sale_id' => $sale->id,
            'subtotal' => $sale->subtotal,
            'shipping_cost' => $sale->shipping_cost,
            'shipping_method' => $sale->shippingMethod,
            'total' => $sale->total,
            'products' => $sale->products,
            'coupon' => $log->coupon,
        ];

        return $this->success($data, 'Carrito obtenido correctamente');
    }

    // 📌 Listar carritos abandonados (con filtros de fecha, conversión y mail de origen)
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $query = AbandonedCartLog::query()
            ->with(['sale.client', 'coupon'])
            ->orderBy('abandoned_at', 'desc');

        if ($request->has('from_date')) {
            $fromDate = Carbon::parse($request->query('from_date'), 'America/Argentina/Buenos_Aires')
                ->startOfDay()
                ->setTimezone('UTC');
            $query->where('abandoned_at', '>=', $fromDate);
        }

        if ($request->has('to_date')) {
            $toDate = Carbon::parse($request->query('to_date'), 'America/Argentina/Buenos_Aires')
                ->endOfDay()
                ->setTimezone('UTC');
            $query->where('abandoned_at', '<=', $toDate);
        }

        if ($request->has('converted')) {
            $converted = $request->query('converted');
            if ($converted == 'true' || $converted == '1') {
                $query->whereNotNull('converted_at');
            } else {
                $query->whereNull('converted_at');
            }
        }

        if ($request->has('converted_via')) {
            $query->where('converted_via', $request->query('converted_via'));
        }

        if (!$perPage) {
            $logs = $query->get();
            return $this->success($logs, 'Carritos abandonados obtenidos');
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        $metaData = [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'from' => $logs->firstItem(),
            'to' => $logs->lastItem(),
        ];

        return $this->success($logs->items(), 'Carritos abandonados obtenidos', $metaData);
    }
}
