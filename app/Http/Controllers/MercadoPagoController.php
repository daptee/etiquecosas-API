<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SaleStatusHistory;
use App\Services\StockService;
use App\Mail\OrderSummaryMail;
use App\Mail\OrderSummaryMailTo;
use App\Jobs\GenerateSalePdfsJob;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
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

            if (!empty($sale->shipping_cost) && $sale->shipping_cost > 0) {
                $items[] = [
                    'id' => 'shipping',
                    'title' => 'Costo de envío',
                    'description' => 'Envío de la venta',
                    'quantity' => 1,
                    'unit_price' => (float) $sale->shipping_cost,
                    'currency_id' => 'ARS'
                ];
            }
        };

        // Aplicar descuento si llega discount_amount
        $discountAmount = (float) ($sale->discount_amount ?? 0);

        if ($discountAmount > 0) {
            $items[] = [
                'id' => 'discount',
                'title' => 'Descuento',
                'description' => 'Descuento aplicado a la compra',
                'quantity' => 1,
                'unit_price' => -$discountAmount, // valor negativo para restar
                'currency_id' => 'ARS'
            ];
        }

        Log::info('services.mercadopago.token: ' . config('services.mercadopago.token'));

        $url_front = config('services.front_url');

        $backUrls = [
            "success" => $url_front . "/payment/success",
            "failure" => $url_front . "/payment/success",
            "pending" => $url_front . "/payment/success",
        ];

        Log::info('Back URLs: ' . json_encode($backUrls));

        $platformId = config('services.mercadopago.platform_id');

        Log::info('mercado pago platform id' . $platformId);

        // Crear preferencia vía HTTP
        $response = Http::withToken(config('services.mercadopago.token'))
            ->withHeaders([
                'X-Platform-Id' => $platformId
            ])
            ->post('https://api.mercadopago.com/checkout/preferences', [
                "items" => $items,
                "back_urls" => $backUrls,
                "auto_return" => "approved",
                "external_reference" => (string) $sale->id,
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

    /**
     * Webhook de Mercado Pago para recibir notificaciones de pagos
     * Documentación: https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/additional-content/your-integrations/notifications/webhooks
     */
    public function webhook(Request $request)
    {
        try {
            // Log de la notificación recibida
            Log::info('Webhook MercadoPago recibido', [
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // Verificar que sea una notificación de tipo payment
            $type = $request->input('type');
            $action = $request->input('action');

            if ($type !== 'payment') {
                Log::info('Webhook ignorado: no es tipo payment', ['type' => $type]);
                return response()->json(['status' => 'ignored'], 200);
            }

            // Obtener el ID del pago desde la notificación
            $paymentId = $request->input('data.id');

            if (!$paymentId) {
                Log::warning('Webhook sin payment ID');
                return response()->json(['error' => 'Missing payment ID'], 400);
            }

            // Consultar el pago a la API de Mercado Pago para obtener detalles
            $response = Http::withToken(config('services.mercadopago.token'))
                ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

            if (!$response->successful()) {
                Log::error('Error al consultar pago en MP', [
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return response()->json(['error' => 'Payment not found'], 404);
            }

            $paymentData = $response->json();

            Log::info('Datos del pago obtenidos', [
                'payment_id' => $paymentId,
                'status' => $paymentData['status'] ?? null,
                'external_reference' => $paymentData['external_reference'] ?? null,
                'payment_type_id' => $paymentData['payment_type_id'] ?? null,
                'payment_method_id' => $paymentData['payment_method_id'] ?? null
            ]);

            // Obtener el external_reference (ID de nuestra venta)
            $saleId = $paymentData['external_reference'] ?? null;
            $paymentStatus = $paymentData['status'] ?? null;
            $paymentTypeId = $paymentData['payment_type_id'] ?? null;
            $paymentMethodId = $paymentData['payment_method_id'] ?? null;

            if (!$saleId) {
                Log::warning('Webhook sin external_reference (sale_id)');
                return response()->json(['error' => 'Missing external reference'], 400);
            }

            // Buscar la venta
            $sale = Sale::find($saleId);

            if (!$sale) {
                Log::error('Venta no encontrada', ['sale_id' => $saleId]);
                return response()->json(['error' => 'Sale not found'], 404);
            }

            // IMPORTANTE: Validar que el estado de la venta permita procesar el webhook
            // Estados válidos:
            // - 8: Pendiente de pago (permite procesar approved y rejected)
            // - 9: Pago rechazado (permite procesar approved para reintentos exitosos)
            $validStatuses = [8, 9]; // Pendiente de pago, Pago rechazado

            if (!in_array($sale->sale_status_id, $validStatuses)) {
                Log::info('Venta ignorada: no está en estado válido para procesar webhook', [
                    'sale_id' => $sale->id,
                    'current_status' => $sale->sale_status_id,
                    'payment_status' => $paymentStatus
                ]);
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Sale is not in a valid status for payment processing'
                ], 200);
            }

            // Si la venta está en "Pago rechazado" (9), solo procesar si el nuevo estado es "approved"
            // Esto evita cambiar de "Pago rechazado" a "Pago rechazado" en reintentos fallidos
            if ($sale->sale_status_id == 9 && $paymentStatus !== 'approved') {
                Log::info('Venta en estado rechazado: solo se procesan pagos aprobados', [
                    'sale_id' => $sale->id,
                    'current_status' => $sale->sale_status_id,
                    'payment_status' => $paymentStatus
                ]);
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Sale already rejected, only approved payments are processed'
                ], 200);
            }

            // ⚡ REGLA ESPECIAL: Aprobación automática para ventas mayoristas con pago en efectivo
            // Si es venta mayorista (channel_id = 4) y el pago es en efectivo (rapipago o pagofacil)
            // se aprueba automáticamente sin esperar confirmación del pago
            $isWholesale = $sale->channel_id === 4;
            $isCashPayment = $paymentTypeId === 'ticket' &&
                            in_array($paymentMethodId, ['rapipago', 'pagofacil']);

            if ($isWholesale && $isCashPayment && $paymentStatus === 'pending') {
                Log::info('Auto-aprobando venta mayorista con pago en efectivo', [
                    'sale_id' => $sale->id,
                    'channel_id' => $sale->channel_id,
                    'payment_method' => $paymentMethodId,
                    'payment_type' => $paymentTypeId
                ]);

                // Forzar el estado a 'approved' para procesamiento automático
                $paymentStatus = 'approved';
            }

            // Procesar según el estado del pago
            if ($paymentStatus === 'approved') {
                // 🔒 IMPORTANTE: Verificar que la venta NO haya sido aprobada anteriormente
                if ($sale->hasBeenApproved()) {
                    Log::info('Webhook recibido para venta ya aprobada, se ignora', [
                        'sale_id' => $sale->id,
                        'payment_id' => $paymentId
                    ]);

                    // Continuar sin procesar, pero responder exitosamente para evitar reintentos del webhook
                    $this->logAudit(null, 'Webhook MercadoPago - Already Approved', $request->all(), $sale);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Sale already approved previously',
                        'sale_id' => $sale->id
                    ], 200);
                }

                Log::info('Pago aprobado, cambiando estado de venta', ['sale_id' => $sale->id]);

                // Cambiar estado a "Aprobado" (1)
                $sale->sale_status_id = 1;
                $sale->save();

                // Guardar historial de estado
                SaleStatusHistory::create([
                    'sale_id' => $sale->id,
                    'sale_status_id' => 1,
                    'date' => Carbon::now(),
                ]);

                // Cargar relaciones necesarias
                $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);

                // Enviar emails de confirmación
                try {
                    $notifyEmail = env('MAIL_NOTIFICATION_TO');
                    Mail::to($sale->client->email)->send(new OrderSummaryMail($sale));
                    Mail::to($notifyEmail)->send(new OrderSummaryMailTo($sale));
                    Log::info('Emails de confirmación enviados', ['sale_id' => $sale->id]);
                } catch (\Exception $e) {
                    Log::error('Error enviando emails', [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Descontar stock
                try {
                    StockService::discountStock($sale);
                    Log::info('Stock descontado', ['sale_id' => $sale->id]);
                } catch (\Exception $e) {
                    Log::error('Error descontando stock', [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Generar PDFs de etiquetas de forma asíncrona (no bloquea el webhook)
                try {
                    GenerateSalePdfsJob::dispatch($sale->id);
                    Log::info('Job de generación de PDFs encolado', ['sale_id' => $sale->id]);
                } catch (\Exception $e) {
                    Log::error('Error encolando job de PDFs', [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage()
                    ]);
                }

                $this->logAudit(null, 'Webhook MercadoPago - Payment Approved', $request->all(), $sale);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale updated to approved',
                    'sale_id' => $sale->id
                ], 200);

            } elseif ($paymentStatus === 'rejected') {
                Log::info('Pago rechazado', ['sale_id' => $sale->id]);

                // Cambiar estado a "Pago rechazado" (9) si existe ese estado
                // Si no, mantener en pendiente de pago
                $sale->sale_status_id = 9; // Asumiendo que existe estado 9 para "Pago rechazado"
                $sale->save();

                SaleStatusHistory::create([
                    'sale_id' => $sale->id,
                    'sale_status_id' => 9,
                    'date' => Carbon::now(),
                ]);

                $this->logAudit(null, 'Webhook MercadoPago - Payment Rejected', $request->all(), $sale);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sale updated to rejected',
                    'sale_id' => $sale->id
                ], 200);

            } else {
                Log::info('Estado de pago no procesado', [
                    'sale_id' => $sale->id,
                    'status' => $paymentStatus
                ]);

                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Payment status not handled',
                    'payment_status' => $paymentStatus
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Error procesando webhook de MercadoPago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
