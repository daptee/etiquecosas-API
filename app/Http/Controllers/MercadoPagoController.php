<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleProduct;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Log;
use Validator;

class MercadoPagoController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    public function createPreference(Request $request)
    {
        $sale = $this->findObject(Sale::class, $request->sale_id);

        $sale->load(['products.product', 'products.variant'])
            ->findOrFail($request->sale_id);

        // si sale no existe, devolver error
        if (!$sale) {
            return response()->json(['error' => 'Venta no encontrada'], 404);
        }

        // si sale_status_id no es 8 (Pendiente de pago), devolver error
        if ($sale->sale_status_id != 8) {
            return response()->json(['error' => 'La venta no esta disponible'], 400);
        }


        // Preparar items
        $items = [];
        if ($sale) {
            foreach ($sale->products as $salesProduct) {
                Log::info('Processing SaleProduct variant: ' . ($salesProduct->variant ? $salesProduct->variant->name : 'No variant'));
                $items[] = [
                    'id' => $salesProduct->id,
                    'title' => $salesProduct->product ? $salesProduct->product->name : 'Producto',
                    'description' => $salesProduct->comment ?? '',
                    'quantity' => $salesProduct->quantity,
                    'unit_price' => (float) $salesProduct->unit_price,
                    'currency_id' => 'ARS'
                ];
            }
        }
        ;

        Log::info('services.mercadopago.token: ' . config('services.mercadopago.token'));

        $url_front = config('services.front_url');

        $backUrls = [
            "success" => $url_front . "/payment/success",
            "failure" => $url_front . "/payment/success",
            "pending" => $url_front . "/payment/success",
        ];

        Log::info('Back URLs: ' . json_encode($backUrls));

        // Crear preferencia vía HTTP
        $response = Http::withToken(config('services.mercadopago.token'))
            ->post('https://api.mercadopago.com/checkout/preferences', [
                "items" => $items,
                "back_urls" => $backUrls,
                "auto_return" => "approved",
            ]);

        $data = $response->json();

        return response()->json([
            'message' => 'Información de pago creada',
            'data' => [
                'init_point' => $data['init_point'] ?? null
            ]
        ]);
    }

    public function success(Request $request)
    {
        return response()->json([
            'message' => 'Pago aprobado',
            'query' => $request->all(),
        ]);
    }

    public function failure(Request $request)
    {
        return response()->json([
            'message' => 'Pago fallido',
            'query' => $request->all(),
        ]);
    }

    public function pending(Request $request)
    {
        return response()->json([
            'message' => 'Pago pendiente',
            'query' => $request->all(),
        ]);
    }
}
