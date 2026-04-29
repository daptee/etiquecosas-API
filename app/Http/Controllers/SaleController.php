<?php

namespace App\Http\Controllers;

use App\Exports\SalesExport;
use App\Exports\WholesaleSalesExport;
use App\Mail\OrderSummaryMail;
use App\Mail\OrderSummaryMailTo;
use App\Mail\OrderProductionsMail;
use App\Mail\OrderSendMail;
use App\Mail\OrderRetiredMail;
use App\Mail\OrderWithdrawMail;
use App\Mail\OrderAlmostReadyMail;
use App\Mail\WelcomeMail;
use App\Models\Channel;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductPdf;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SaleStatus;
use App\Models\SaleStatusHistory;
use App\Services\BandaService;
use App\Services\CintaCoserService;
use App\Services\CintaPlancharService;
use App\Services\EtiquetaService;
use App\Services\SelloService;
use App\Services\StockService;
use App\Traits\ApiResponse;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Validator;
use App\Traits\Auditable;

class SaleController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    // 📌 Listar ventas
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $user = Auth::user();

        $query = Sale::query()
            ->with([
                'client',
                'channel:id,name',
                'products.product',
                'products.variant',
                'status:id,name',
                'statusHistory',
                'shippingMethod',
                'coupons',
                'user',
                'cadete',
                'locality',
                'childSales',
                'parentSale'
            ])
            ->orderBy('created_at', 'desc');

        // Si es diseñador (profile_id = 2), solo mostrar ventas asignadas a él
        if ($user && $user->profile_id === 2) {
            $query->where('user_id', $user->id);
        }

        // Si es cadete (profile_id = 4), solo mostrar ventas asignadas a él
        if ($user && $user->profile_id === 4) {
            $query->where('cadete_id', $user->id);
        }

        // 🔹 Buscador
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    // Buscar por ID de venta
                    $q->where('id', $search);
                } else {
                    $q->where(function ($q2) use ($search) {
                        // Buscar por nombre de producto
                        $q2->whereHas('products.product', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%{$search}%");
                        })
                        // Buscar por nombre/apellido en personalización (case-insensitive)
                        ->orWhereHas('products', function ($q3) use ($search) {
                            // Buscar específicamente en los campos name y lastName dentro de form
                            $q3->where(function ($q4) use ($search) {
                                $searchLower = strtolower($search);
                                $q4->whereRaw('LOWER(customization_data) like ?', ["%form%name%{$searchLower}%"])
                                   ->orWhereRaw('LOWER(customization_data) like ?', ["%form%lastName%{$searchLower}%"]);
                            });
                        })
                        // Buscar por email del cliente
                        ->orWhereHas('client', function ($q3) use ($search) {
                            $q3->where('email', 'like', "%{$search}%");
                        })
                        // Buscar por dirección
                        ->orWhere('address', 'like', "%{$search}%")
                        // Buscar por nombre de localidad
                        ->orWhereHas('locality', function ($q3) use ($search) {
                            $q3->where('name', 'like', "%{$search}%");
                        });
                    });
                }
            });
        }

        if ($request->has('channel_id')) {
            $channelIds = $request->query('channel_id');
            if (is_array($channelIds)) {
                $query->whereIn('channel_id', $channelIds);
            } else {
                $query->where('channel_id', $channelIds);
            }
        }

        // 🔹 Filtros
        if ($request->has('sale_status_id')) {
            $statusIds = $request->query('sale_status_id');
            if (is_array($statusIds)) {
                $query->whereIn('sale_status_id', $statusIds);
            } else {
                $query->where('sale_status_id', $statusIds);
            }
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->has('assigned_user')) {
            $assignedUser = $request->query('assigned_user');
            if ($assignedUser == 'true' || $assignedUser == '1') {
                $query->whereNotNull('user_id');
            } else {
                $query->whereNull('user_id');
            }
        }

        if ($request->has('cadete_id')) {
            $query->where('cadete_id', $request->query('cadete_id'));
        }

        if ($request->has('assigned_cadete')) {
            $assignedCadete = $request->query('assigned_cadete');
            if ($assignedCadete == 'true' || $assignedCadete == '1') {
                $query->whereNotNull('cadete_id');
            } else {
                $query->whereNull('cadete_id');
            }
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->query('client_id'));
        }

        // 🔹 Tipo de envío: retiro / envío / todos
        if ($request->has('shipping_method_id')) {
            $query->where('shipping_method_id', $request->query('shipping_method_id'));
        }

        // 🔹 Filtro por estado de pago: 'paid' (cobradas) o 'unpaid' (no cobradas)
        // Este filtro debe aplicarse ANTES del filtro de fechas para determinar qué fecha usar
        $paymentStatus = $request->query('payment_status');
        if ($request->has('payment_status')) {
            if ($paymentStatus === 'unpaid') {
                // Solo ventas NO cobradas: Pendiente de pago (8) o Pago rechazado (9)
                $query->whereIn('sale_status_id', [8, 9]);
            } elseif ($paymentStatus === 'paid') {
                // Solo ventas cobradas: todas excepto 8 y 9
                $query->whereNotIn('sale_status_id', [8, 9]);
            }
            // Si se envía otro valor, no se aplica filtro
        } else {
            // Comportamiento por defecto: solo ventas cobradas
            $query->whereNotIn('sale_status_id', [8, 9]);
            $paymentStatus = 'paid'; // Establecer explícitamente para el filtro de fechas
        }

        // 🔹 Rango de fechas (convertir desde zona horaria Argentina a UTC para comparar)
        // Para ventas pagadas: filtrar por fecha de aprobación (estado_id = 1 en sales_status_history)
        // Para ventas no pagadas: filtrar por fecha de creación (created_at)
        if ($request->has('from_date') || $request->has('to_date')) {
            if ($paymentStatus === 'paid') {
                // Ventas pagadas: filtrar por fecha de aprobación
                if ($request->has('from_date')) {
                    $fromDate = Carbon::parse($request->query('from_date'), 'America/Argentina/Buenos_Aires')
                        ->startOfDay()
                        ->setTimezone('UTC');

                    $query->whereHas('statusHistory', function ($q) use ($fromDate) {
                        $q->where('sale_status_id', 1) // Estado "Aprobado"
                          ->where('date', '>=', $fromDate);
                    });
                }

                if ($request->has('to_date')) {
                    $toDate = Carbon::parse($request->query('to_date'), 'America/Argentina/Buenos_Aires')
                        ->endOfDay()
                        ->setTimezone('UTC');

                    $query->whereHas('statusHistory', function ($q) use ($toDate) {
                        $q->where('sale_status_id', 1) // Estado "Aprobado"
                          ->where('date', '<=', $toDate);
                    });
                }
            } else {
                // Ventas no pagadas: filtrar por fecha de creación
                if ($request->has('from_date')) {
                    $fromDate = Carbon::parse($request->query('from_date'), 'America/Argentina/Buenos_Aires')
                        ->startOfDay()
                        ->setTimezone('UTC');
                    $query->where('created_at', '>=', $fromDate);
                }

                if ($request->has('to_date')) {
                    $toDate = Carbon::parse($request->query('to_date'), 'America/Argentina/Buenos_Aires')
                        ->endOfDay()
                        ->setTimezone('UTC');
                    $query->where('created_at', '<=', $toDate);
                }
            }
        }

        // 🔹 Si no hay perPage, traer todo
        if (!$perPage) {
            $sales = $query->get();
            return $this->success($sales, 'Ventas obtenidas');
        }

        // 🔹 Paginación
        $sales = $query->paginate($perPage, ['*'], 'page', $page);

        $metaData = [
            'current_page' => $sales->currentPage(),
            'last_page' => $sales->lastPage(),
            'per_page' => $sales->perPage(),
            'total' => $sales->total(),
            'from' => $sales->firstItem(),
            'to' => $sales->lastItem(),
        ];

        return $this->success($sales->items(), 'Ventas obtenidas', $metaData);
    }


    // 📌 Obtener detalle de una venta
    public function show($id)
    {
        $sale = $this->findObject(Sale::class, $id);
        // Si no se encuentra el producto, retornar error 404
        if (!$sale) {
            return $this->error('Producto no encontrado', 404);
        }
        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'shippingMethod', 'coupons', 'user', 'childSales', 'parentSale'])
            ->findOrFail($id);

        return $this->success($sale, 'Venta obtenida correctamente');

    }

    public function showRecort($id)
    {
        $sale = $this->findObject(Sale::class, $id);

        if (!$sale) {
            return $this->error('Venta no encontrada', 404);
        }

        // Cargar solo las relaciones necesarias
        $sale->load(['products.product.images', 'products.variant', 'status', 'coupons']);

        // Preparar la respuesta resumida
        $data = [
            'id' => $sale->id,
            'subtotal' => $sale->subtotal,
            'total' => $sale->total,
            'shipping_cost' => $sale->shipping_cost,
            'shipping_method_id' => $sale->shipping_method_id,
            'payment_method_id' => $sale->payment_method_id,
            'discount_amount' => $sale->discount_amount,
            'status' => $sale->status,
            'products' => $sale->products,
            'coupons' => $sale->coupons,
        ];

        return $this->success($data, 'Venta resumida obtenida correctamente');
    }

    // 📌 Crear una venta
    public function store(Request $request)
    {
        $rules = [
            'client_mail' => 'required|string|email|max:255',
            'client_name' => 'nullable|string|max:255',
            'client_lastname' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:50',
            'client_address' => 'nullable|string|max:255',
            'client_locality_id' => 'nullable|integer|exists:localities,id',
            'client_postal_code' => 'nullable|string|max:20',
            'channel_id' => 'required|integer|exists:channels,id',
            'external_id' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string|max:255',
            'shipping_locality_id' => 'nullable|integer|exists:localities,id',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_save' => 'nullable|boolean',
            'client_shipping_id' => 'nullable|integer|exists:client_shippings,id',
            'subtotal' => 'required|numeric|min:0',
            'shipping_cost' => 'required|numeric|min:0',
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'customer_notes' => 'nullable|string',
            'internal_comments' => 'nullable|string',
            'sale_status_id' => 'required|integer|exists:sale_status,id',
            'sale_id' => 'nullable|integer|exists:sales,id',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'discount_amount' => 'nullable|numeric|min:0',
            'coupons' => 'nullable|array',
            'coupons.*.coupon_code' => 'required_with:coupons|string|exists:coupons,code',
            'coupons.*.discount_amount' => 'required_with:coupons|numeric|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.comment' => 'nullable|string',
            'products.*.customization_data' => 'nullable|json',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(null, 'Sale Validation Fail (Create)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // buscar si existe el cliente por email nada mas no creamos nada aun
        $client = Client::firstWhere('email', $request->client_mail);

        if (!$client) {
            // validamos que los datos necesarios para crear el cliente esten
            $clientRules = [
                'client_name' => 'required|string|max:255',
                'client_lastname' => 'required|string|max:255',
                'client_phone' => 'nullable|string|max:50',
                'client_postal_code' => 'nullable|string|max:20',
                'client_address' => 'nullable|string|max:255',
                'client_locality_id' => 'nullable|integer|exists:localities,id',
            ];

            $clientValidator = Validator::make($request->all(), $clientRules);
            if ($clientValidator->fails()) {
                $this->logAudit(null, 'Client Validation Fail (Create)', $request->all(), $clientValidator->errors());
                return $this->validationError($clientValidator->errors());
            }

            // creamos una password random para el cliente
            $randomPassword = bin2hex(random_bytes(4)); // 8 caracteres
            $request->merge(['password' => $randomPassword]);

            // si no existe, creamos el cliente
            $client = Client::create([
                'client_type_id' => 1, // tipo consumidor final
                'name' => $request->client_name,
                'lastName' => $request->client_lastname,
                'password' => bcrypt($randomPassword),
                'email' => $request->client_mail,
                'phone' => $request->client_phone,
                'status_id' => 1,
            ]);

            $mailData = [
                'name' => $client->name . ' ' . $client->lastName,
                'password' => $randomPassword,
                'email' => $client->email,
            ];
            Mail::to($request->client_mail)->send(new WelcomeMail($mailData));

            if ($request->client_address && $request->client_locality_id) {
                ClientAddress::create([
                    'client_id' => $client->id,
                    'address' => $request->client_address,
                    'locality_id' => $request->client_locality_id,
                    'postal_code' => $request->client_postal_code ?? null,
                ]);
            }

            $this->logAudit(null, 'Store Client Sale', $request->all(), $client);
        }

        // agregamos el client_id al request para crear la venta
        $request->merge(['client_id' => $client->id]);

        $subtotal = $request->subtotal ?? 0;
        $shippingCost = $request->shipping_cost ?? 0;
        $total = $subtotal + $shippingCost;

        $sale = Sale::create([
            'client_id' => $client->id,
            'channel_id' => $request->channel_id,
            'external_id' => $request->external_id,
            'address' => $request->shipping_address, // 👈 se llena
            'locality_id' => $request->shipping_locality_id,
            'postal_code' => $request->shipping_postal_code,
            'client_shipping_id' => $request->client_shipping_id,
            'subtotal' => $request->subtotal,
            'total' => $total,
            'shipping_cost' => $request->shipping_cost,
            'shipping_method_id' => $request->shipping_method_id,
            'payment_method_id' => 1,
            'customer_notes' => $request->customer_notes,
            'internal_comments' => $request->internal_comments,
            'sale_status_id' => $request->sale_status_id,
            'sale_id' => $request->sale_id,
        ]);

        if ($request->shipping_save) {
            ClientAddress::create([
                'client_id' => $client->id,
                'address' => $request->shipping_address,
                'locality_id' => $request->shipping_locality_id ?? null,
                'postal_code' => $request->shipping_postal_code ?? null,
            ]);
        }

        if ($request->coupons) {
            foreach ($request->coupons as $c) {
                $coupon = Coupon::where('code', $c['coupon_code'])->first();
                if ($coupon) {
                    $sale->coupons()->attach($coupon->id, ['discount_amount' => $c['discount_amount']]);
                }
            }
        } elseif ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();
            if ($coupon) {
                $sale->coupons()->attach($coupon->id, ['discount_amount' => $request->discount_amount ?? 0]);
            }
        }


        // Guardar historial de estado
        SaleStatusHistory::create([
            'sale_id' => $sale->id,
            'sale_status_id' => $sale->sale_status_id,
            'date' => Carbon::now(),
        ]);

        // Guardar productos de la venta
        foreach ($request->products as $product) {
            $sale->products()->create($product);
        }

        $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality', 'coupons']);

        // Ventas mayoristas se aprueban automáticamente al crear la reserva
        if ($sale->channel_id === 4) {
            $sale->sale_status_id = 1;
            $sale->save();
            Log::channel('meta_capi')->info('[store mayorista] Venta aprobada automáticamente → disparando CAPI', ['sale_id' => $sale->id]);
            $this->approveSale($sale);
        }

        $this->logAudit(Auth::user() ?? null, 'Add Sale', $request->all(), $sale);
        return $this->success($sale, 'Venta creada correctamente');
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'address' => 'nullable|string|max:255',
            'locality_id' => 'nullable|integer|exists:localities,id',
            'postal_code' => 'nullable|string|max:20',
            'subtotal' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'shipping_method_id' => 'nullable|integer|exists:shipping_methods,id',
            'customer_notes' => 'nullable|string',
            'internal_comments' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Calcular total (subtotal + shipping_cost)
        $subtotal = $request->subtotal ?? 0;
        $shippingCost = $request->shipping_cost ?? 0;
        $total = $subtotal + $shippingCost;

        $sale = Sale::findOrFail($id);
        $data = $request->only(array_keys($rules));
        $data['total'] = $total;
        $sale->update($data);

        $sale->load(['products.variant', 'statusHistory', 'coupons']);

        $this->logAudit(Auth::user() ?? null, 'Update Sale', $request->all(), $sale);
        return $this->success($sale, 'Venta actualizada correctamente');
    }

    // 📌 Eliminar venta
    public function destroy($id)
    {
        $sale = Sale::findOrFail($id);
        $sale->delete();

        $this->logAudit(Auth::user() ?? null, 'Delete Sale', $id, $sale);
        return $this->success($sale, 'Venta eliminada correctamente');
    }

    // 📌 Cambiar estado de venta
    public function changeStatus(Request $request, $id)
    {
        $sale = Sale::findOrFail($id);
        $saleStatusOld = $sale->sale_status_id;

        // 🔒 IMPORTANTE: Verificar que el status 1 (aprobado) solo se asigne UNA VEZ
        if ($request->sale_status_id == 1 && $sale->hasBeenApproved()) {
            $this->logAudit(Auth::user() ?? null, 'Sale Status Change Ignored', $request->all(), 'La venta ya fue aprobada anteriormente. Se ignora el cambio de status.');

            // Continuar sin actualizar el status, pero cargar la venta con sus relaciones
            $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality', 'products.variant', 'statusHistory']);
            return $this->success($sale, 'La venta ya fue aprobada anteriormente');
        }

        $sale->sale_status_id = $request->sale_status_id;
        $sale->save();

        $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);

        if ($sale->sale_status_id == 2) { // estado "en producción"
            Mail::to($sale->client->email)->send(new OrderProductionsMail($sale));
            // Guardar historial
            SaleStatusHistory::create([
                'sale_id' => $sale->id,
                'sale_status_id' => $request->sale_status_id,
                'date' => Carbon::now(),
            ]);
        }

        if ($sale->sale_status_id == 3 && $sale->shipping_method_id != 1) { // envío a domicilio estado "enviado"
            Mail::to($sale->client->email)->send(new OrderSendMail($sale));
            // Guardar historial
            SaleStatusHistory::create([
                'sale_id' => $sale->id,
                'sale_status_id' => $request->sale_status_id,
                'date' => Carbon::now(),
            ]);
        }

        if ($sale->sale_status_id == 3 && $sale->shipping_method_id == 1) { // retiro por local estado "Listo para retirar"
            Mail::to($sale->client->email)->send(new OrderWithdrawMail($sale));
            // Guardar historial
            SaleStatusHistory::create([
                'sale_id' => $sale->id,
                'sale_status_id' => $request->sale_status_id,
                'date' => Carbon::now(),
            ]);
        }

        if ($sale->sale_status_id == 6) { // estado "Pedido casi listo"
            Mail::to($sale->client->email)->send(new OrderAlmostReadyMail($sale));
            // Guardar historial
            SaleStatusHistory::create([
                'sale_id' => $sale->id,
                'sale_status_id' => $request->sale_status_id,
                'date' => Carbon::now(),
            ]);
        }

        if ($sale->sale_status_id == 4 && $sale->shipping_method_id != 1) { // estado "Entregado"
            Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
            // Guardar historial
            SaleStatusHistory::create([
                'sale_id' => $sale->id,
                'sale_status_id' => $request->sale_status_id,
                'date' => Carbon::now(),
            ]);
        }

        if ($sale->sale_status_id == 7 && $sale->shipping_method_id == 1) { // estado "Retirado"
            Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
            // Guardar historial
            SaleStatusHistory::create([
                'sale_id' => $sale->id,
                'sale_status_id' => $request->sale_status_id,
                'date' => Carbon::now(),
            ]);
        }

        if ($sale->sale_status_id == 1 && $saleStatusOld != 1) {
            Log::channel('meta_capi')->info('[changeStatus] Venta aprobada → disparando CAPI', ['sale_id' => $sale->id]);
            $this->approveSale($sale);
        }

        $sale->load(['products.variant', 'statusHistory']);

        $this->logAudit(Auth::user() ?? null, 'Update Status Sale', $request->all(), $sale);
        return $this->success($sale, 'Estado de venta actualizada correctamente');
    }

    private function approveSale(Sale $sale): void
    {
        SaleStatusHistory::create([
            'sale_id' => $sale->id,
            'sale_status_id' => 1,
            'date' => Carbon::now(),
        ]);

        /* $this->sendMetaCapiPurchaseEvent($sale); */

        $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);

        $notifyEmail = env('MAIL_NOTIFICATION_TO');

        Mail::to($sale->client->email)->send(new OrderSummaryMail($sale));
        Mail::to($notifyEmail)->send(new OrderSummaryMailTo($sale));

        StockService::discountStock($sale);

        $fechaAprobacion = Carbon::now();

        EtiquetaService::limpiarPdfsDelPedido($sale->id, $fechaAprobacion);
        CintaCoserService::limpiarEtiquetasDeVenta($sale->id, $fechaAprobacion);
        CintaPlancharService::limpiarEtiquetasDeVenta($sale->id, $fechaAprobacion);
        BandaService::limpiarBandasDeVenta($sale->id, $fechaAprobacion);
        SelloService::limpiarSellosDeVenta($sale->id, $fechaAprobacion);

        foreach ($sale->products as $productOrder) {
            $customData = json_decode($productOrder->customization_data, true);

            $form = $customData['form'] ?? [];
            $nombreCompleto = trim(($form['name'] ?? '') . ' ' . ($form['lastName'] ?? ''));

            $customColor = $customData['color']['color_code'] ?? null;
            $customIcon = $customData['icon']['icon'] ?? null;

            if ($customIcon && $customData['icon']['name'] == 'Sin dibujo') {
                $customIcon = null;
            }

            // === SELLOS PERSONALIZADOS (Producto 481) ===
            if (SelloService::esProductoSello($productOrder->product_id)) {
                try {
                    SelloService::agregarSelloAlPdf(
                        $sale->id,
                        $productOrder,
                        $nombreCompleto,
                        $customColor,
                        $customIcon,
                        $fechaAprobacion
                    );
                    Log::info("Sello agregado para {$nombreCompleto}");
                } catch (\Throwable $e) {
                    Log::error("Error agregando sello", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                }
            }

            // === BANDAS (Productos 52944 y 52796) ===
            if (BandaService::esProductoBanda($productOrder->product_id)) {
                try {
                    BandaService::agregarBandaAlPdf(
                        $sale->id,
                        $productOrder,
                        $nombreCompleto,
                        $customColor,
                        $customIcon,
                        $fechaAprobacion
                    );
                    Log::info("Banda agregada para {$nombreCompleto}");
                } catch (\Throwable $e) {
                    Log::error("Error agregando banda", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                }
            }

            // === CINTAS PARA COSER (Producto 1291) ===
            if (CintaCoserService::esProductoCoser($productOrder->product_id)) {
                try {
                    CintaCoserService::agregarEtiquetaAlPdf(
                        $sale->id,
                        $productOrder,
                        $nombreCompleto,
                        $customColor,
                        $customIcon,
                        $fechaAprobacion
                    );
                    Log::info("Etiqueta de cinta para coser agregada para {$nombreCompleto}");
                } catch (\Throwable $e) {
                    Log::error("Error agregando etiqueta de cinta para coser", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                }
            }

            // === CINTAS PARA PLANCHAR (Producto 1247) ===
            if (CintaPlancharService::esProductoPlanchar($productOrder->product_id)) {
                try {
                    CintaPlancharService::agregarEtiquetaAlPdf(
                        $sale->id,
                        $productOrder,
                        $nombreCompleto,
                        $customColor,
                        $customIcon,
                        $fechaAprobacion
                    );
                    Log::info("Etiqueta de cinta para planchar agregada para {$nombreCompleto}");
                } catch (\Throwable $e) {
                    Log::error("Error agregando etiqueta de cinta para planchar", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                }
            }

            $variant = $productOrder->variant?->variant;
            $productPdf = ProductPdf::where('product_id', $productOrder->product_id)->first();

            if ($productPdf) {
                Log::info($productPdf);

                $tematicasGuardadas = $productPdf['data']['tematicas'] ?? [];
                Log::info("Temáticas guardadas en ProductPdf: " . count($tematicasGuardadas));

                if ($variant) {
                    $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                    if (!$tematicaId) {
                        Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                        continue;
                    }

                    $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);

                    if ($tematicaCoincidente) {
                        try {
                            $pdfPaths[] = EtiquetaService::generarEtiquetas(
                                $sale->id,
                                $tematicaId,
                                [$nombreCompleto],
                                $productOrder,
                                $tematicaCoincidente,
                                $customColor,
                                $customIcon,
                                $fechaAprobacion
                            );

                            Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                            continue;
                        } catch (\Throwable $e) {
                            Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                'error' => $e->getMessage(),
                                'product_order_id' => $productOrder->id,
                            ]);
                            continue;
                        }
                    }
                } else {
                    foreach ($tematicasGuardadas as $tematica) {
                        $tematicaId = $tematica['id'] ?? null;

                        try {
                            $pdfPaths[] = EtiquetaService::generarEtiquetas(
                                $sale->id,
                                $tematicaId,
                                [$nombreCompleto],
                                $productOrder,
                                $tematica,
                                $customColor,
                                $customIcon,
                                $fechaAprobacion
                            );

                            Log::info("PDF generado sin variante para {$nombreCompleto}, temática ID: {$tematicaId}");
                        } catch (\Throwable $e) {
                            Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                'error' => $e->getMessage(),
                                'product_order_id' => $productOrder->id,
                            ]);
                        }
                    }
                }
            }

            Log::info(message: "Sin informacion del pdf en el producto con id: $productOrder->product_id");

            continue;
        }
    }

    public function changeStatusAdmin(Request $request, $id)
    {
        $user = Auth::user();
        $sale = Sale::findOrFail($id);
        $saleStatusOld = $sale->sale_status_id;

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Status)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        // 🔒 IMPORTANTE: Verificar que el status 1 (aprobado) solo se asigne UNA VEZ
        if ($request->sale_status_id == 1 && $sale->hasBeenApproved()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Change Status Admin)', $request->all(), 'La venta ya fue aprobada anteriormente. No se puede volver a aprobar.');
            return $this->error('La venta ya fue aprobada anteriormente. No se puede volver a aprobar.', 400);
        }

        $sale->sale_status_id = $request->sale_status_id;
        $sale->save();

        // Guardar historial
        SaleStatusHistory::create([
            'sale_id' => $sale->id,
            'sale_status_id' => $request->sale_status_id,
            'date' => Carbon::now(),
        ]);

        $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);

        if ($sale->sale_status_id == 2) { // estado "en producción"
            Mail::to($sale->client->email)->send(new OrderProductionsMail($sale));
        }

        if ($sale->sale_status_id == 3 && $sale->shipping_method_id != 1) { // envío a domicilio estado "enviado"
            Mail::to($sale->client->email)->send(new OrderSendMail($sale));
        }

        if ($sale->sale_status_id == 3 && $sale->shipping_method_id == 1) { // retiro por local estado "Listo para retirar"
            Mail::to($sale->client->email)->send(new OrderWithdrawMail($sale));
        }

        if ($sale->sale_status_id == 6) { // estado "Pedido casi listo"
            Mail::to($sale->client->email)->send(new OrderAlmostReadyMail($sale));
        }

        if ($sale->sale_status_id == 4 && $sale->shipping_method_id != 1) { // estado "Entregado"
            Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
        }

        if ($sale->sale_status_id == 7 && $sale->shipping_method_id == 1) { // estado "Retirado"
            Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
        }

        if ($sale->sale_status_id == 1 && $saleStatusOld != 1) {
            Log::channel('meta_capi')->info('[changeStatusAdmin] Venta aprobada → disparando CAPI', ['sale_id' => $sale->id]);
            /* $this->sendMetaCapiPurchaseEvent($sale); */

            $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);

            $notifyEmail = env('MAIL_NOTIFICATION_TO');

            Mail::to($sale->client->email)->send(new OrderSummaryMail($sale));
            Mail::to($notifyEmail)->send(new OrderSummaryMailTo($sale));

            StockService::discountStock($sale);

            // Usar la fecha de aprobación (ahora) en lugar de la fecha de creación de la venta
            $fechaAprobacion = Carbon::now();

            // 🗑️ Eliminar todos los PDFs anteriores de este pedido antes de generar nuevos
            EtiquetaService::limpiarPdfsDelPedido($sale->id, $fechaAprobacion);
            CintaCoserService::limpiarEtiquetasDeVenta($sale->id, $fechaAprobacion);
            CintaPlancharService::limpiarEtiquetasDeVenta($sale->id, $fechaAprobacion);
            BandaService::limpiarBandasDeVenta($sale->id, $fechaAprobacion);
            SelloService::limpiarSellosDeVenta($sale->id, $fechaAprobacion);

            foreach ($sale->products as $productOrder) {
                // === 1. Datos base ===
                $customData = json_decode($productOrder->customization_data, true);

                $form = $customData['form'] ?? [];
                $nombreCompleto = trim(($form['name'] ?? '') . ' ' . ($form['lastName'] ?? ''));

                $customColor = $customData['color']['color_code'] ?? null;
                $customIcon = $customData['icon']['icon'] ?? null;

                if ($customIcon && $customData['icon']['name'] == 'Sin dibujo') {
                    $customIcon = null;
                }

                // === SELLOS PERSONALIZADOS (Producto 481) ===
                if (SelloService::esProductoSello($productOrder->product_id)) {
                    try {
                        SelloService::agregarSelloAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $fechaAprobacion
                        );
                        Log::info("Sello agregado para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando sello", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === BANDAS (Productos 52944 y 52796) ===
                if (BandaService::esProductoBanda($productOrder->product_id)) {
                    try {
                        BandaService::agregarBandaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $fechaAprobacion
                        );
                        Log::info("Banda agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando banda", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === CINTAS PARA COSER (Producto 1291) ===
                if (CintaCoserService::esProductoCoser($productOrder->product_id)) {
                    try {
                        CintaCoserService::agregarEtiquetaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $fechaAprobacion
                        );
                        Log::info("Etiqueta de cinta para coser agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando etiqueta de cinta para coser", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === CINTAS PARA PLANCHAR (Producto 1247) ===
                if (CintaPlancharService::esProductoPlanchar($productOrder->product_id)) {
                    try {
                        CintaPlancharService::agregarEtiquetaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $fechaAprobacion
                        );
                        Log::info("Etiqueta de cinta para planchar agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando etiqueta de cinta para planchar", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                $variant = $productOrder->variant?->variant;
                $productPdf = ProductPdf::where('product_id', $productOrder->product_id)->first();

                // === 2. Si hay un ProductPdf configurado ===
                if ($productPdf) {
                    Log::info($productPdf);

                    $tematicasGuardadas = $productPdf['data']['tematicas'] ?? [];
                    Log::info("Temáticas guardadas en ProductPdf: " . count($tematicasGuardadas));

                    if ($variant) {
                        $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                        if (!$tematicaId) {
                            Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                            continue;
                        }

                        // Buscar la temática correspondiente
                        $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);

                        if ($tematicaCoincidente) {
                            try {
                                $pdfPaths[] = EtiquetaService::generarEtiquetas(
                                    $sale->id,
                                    $tematicaId,
                                    [$nombreCompleto],
                                    $productOrder,
                                    $tematicaCoincidente,
                                    $customColor,
                                    $customIcon,
                                    $fechaAprobacion
                                );

                                Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                                continue;
                            } catch (\Throwable $e) {
                                Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                                continue;
                            }
                        }
                    } else {
                        // Sin variant: generar PDF por cada temática guardada
                        foreach ($tematicasGuardadas as $tematica) {
                            $tematicaId = $tematica['id'] ?? null;

                            try {
                                $pdfPaths[] = EtiquetaService::generarEtiquetas(
                                    $sale->id,
                                    $tematicaId,
                                    [$nombreCompleto],
                                    $productOrder,
                                    $tematica,
                                    $customColor,
                                    $customIcon,
                                    $fechaAprobacion
                                );

                                Log::info("PDF generado sin variante para {$nombreCompleto}, temática ID: {$tematicaId}");
                            } catch (\Throwable $e) {
                                Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                            }
                        }
                    }
                }

                Log::info(message: "Sin informacion del pdf en el producto con id: $productOrder->product_id");

                continue;
            }
        }

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'shippingMethod', 'coupons', 'user', 'childSales', 'parentSale']);

        $this->logAudit(Auth::user() ?? null, 'Update Status Sale', $request->all(), $sale);
        return $this->success($sale, 'Estado de venta actualizada correctamente');
    }

    public function updateInternalComment(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Internal Comment)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $rules = [
            'internal_comments' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Internal Comment)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::findOrFail($id);
        $sale->internal_comments = $request->internal_comments;
        $sale->save();

        $this->logAudit(Auth::user(), 'Update Internal Comment', ['id' => $id], $sale);

        return $this->success($sale, 'Comentario interno actualizado correctamente');
    }

    /**
     * Actualizar los comentarios adicionales del cliente en una venta.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCustomerNotes(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Customer Notes)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $rules = [
            'customer_notes' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Customer Notes)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::findOrFail($id);
        $sale->customer_notes = $request->customer_notes;
        $sale->save();

        $this->logAudit(Auth::user(), 'Update Customer Notes', ['id' => $id], $sale);

        return $this->success($sale, 'Comentarios del cliente actualizados correctamente');
    }

    public function updateClientData(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Client Data)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $rules = [
            'name' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:clients,email',
            'locality_id' => 'nullable|integer|exists:localities,id',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Client Data)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::findOrFail($id);
        $client = $sale->client;

        if (!$client) {
            return $this->error('Cliente no asociado a esta venta', 404);
        }

        $sale->update($request->only([
            'locality_id',
            'postal_code',
            'address'
        ]));

        $client->update($request->only([
            'name',
            'lastName',
            'email',
            'phone'
        ]));

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory']);

        $this->logAudit(Auth::user(), 'Update Client Data From Sale', ['id' => $id], $sale);

        return $this->success($sale, 'Datos del cliente actualizados correctamente');
    }

    public function assignUser(Request $request, $id)
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Assign User)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::findOrFail($id);
        $sale->user_id = $request->user_id; // reemplaza si ya existía
        $sale->save();

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'user']);


        $this->logAudit(Auth::user(), 'Assign User To Sale', ['id' => $id, 'user_id' => $request->user_id], $sale);

        return $this->success($sale, 'Usuario asignado a la venta correctamente');
    }

    public function assignUserToMultipleSales(Request $request)
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'sale_ids' => 'required|array|min:1',
            'sale_ids.*' => 'integer|exists:sales,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sales Validation Fail (Assign User)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sales = Sale::whereIn('id', $request->sale_ids)->get();

        foreach ($sales as $sale) {
            $sale->user_id = $request->user_id; // asigna o reemplaza si ya tenía usuario
            $sale->save();
        }

        // Opcional: cargar relaciones solo una vez
        $sales->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'user']);

        $this->logAudit(Auth::user(), 'Assign User To Multiple Sales', ['sale_ids' => $request->sale_ids, 'user_id' => $request->user_id], $sales);

        return $this->success($sales, 'Usuario asignado correctamente a las ventas seleccionadas');
    }

    public function assignCadete(Request $request, $id)
    {
        $rules = [
            'cadete_id' => 'required|integer|exists:users,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Assign Cadete)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Validar que el usuario sea cadete (profile_id = 4)
        $cadete = \App\Models\User::find($request->cadete_id);
        if (!$cadete || $cadete->profile_id !== 4) {
            return $this->error('El usuario seleccionado no es un cadete', 400);
        }

        $sale = Sale::findOrFail($id);
        $sale->cadete_id = $request->cadete_id;
        $sale->save();

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'user', 'cadete', 'locality']);

        $this->logAudit(Auth::user(), 'Assign Cadete To Sale', ['id' => $id, 'cadete_id' => $request->cadete_id], $sale);

        return $this->success($sale, 'Cadete asignado a la venta correctamente');
    }

    public function assignCadeteMultiple(Request $request)
    {
        $rules = [
            'cadete_id' => 'required|integer|exists:users,id',
            'sale_ids' => 'required|array|min:1',
            'sale_ids.*' => 'integer|exists:sales,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sales Validation Fail (Assign Cadete)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Validar que el usuario sea cadete (profile_id = 4)
        $cadete = \App\Models\User::find($request->cadete_id);
        if (!$cadete || $cadete->profile_id !== 4) {
            return $this->error('El usuario seleccionado no es un cadete', 400);
        }

        $sales = Sale::whereIn('id', $request->sale_ids)->get();

        foreach ($sales as $sale) {
            $sale->cadete_id = $request->cadete_id;
            $sale->save();
        }

        $sales->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'user', 'cadete', 'locality']);

        $this->logAudit(Auth::user(), 'Assign Cadete To Multiple Sales', ['sale_ids' => $request->sale_ids, 'cadete_id' => $request->cadete_id], $sales);

        return $this->success($sales, 'Cadete asignado correctamente a las ventas seleccionadas');
    }

    public function updateReceiverData(Request $request, $id)
    {
        $user = Auth::user();

        // Solo admin (profile_id = 1) puede modificar datos del receptor
        if (!$user || $user->profile_id !== 1) {
            return $this->error('Solo los administradores pueden modificar los datos del receptor', 403);
        }

        $validator = Validator::make($request->all(), [
            'receiver_name' => 'nullable|string|max:255',
            'receiver_dni' => [
                'nullable',
                'string',
                'regex:/^\d{7,8}$|^\d{2}\.\d{3}\.\d{3}$/'
            ],
            'receiver_observations' => 'nullable|string',
        ], [
            'receiver_dni.regex' => 'El DNI debe tener formato argentino (7-8 dígitos o XX.XXX.XXX)',
        ]);

        if ($validator->fails()) {
            $this->logAudit($user, 'Update Receiver Data Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::findOrFail($id);

        if ($request->has('receiver_name')) {
            $sale->receiver_name = $request->receiver_name;
        }
        if ($request->has('receiver_dni')) {
            $sale->receiver_dni = $request->receiver_dni;
        }
        if ($request->has('receiver_observations')) {
            $sale->receiver_observations = $request->receiver_observations;
        }
        $sale->save();

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'user', 'cadete', 'locality']);

        $this->logAudit($user, 'Update Receiver Data', ['id' => $id, 'receiver_name' => $request->receiver_name, 'receiver_dni' => $request->receiver_dni], $sale);

        return $this->success($sale, 'Datos del receptor actualizados correctamente');
    }

    public function storeLocalSale(Request $request)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Client Data)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $rules = [
            'client_id' => 'nullable|integer|exists:clients,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.comment' => 'nullable|string',
            'products.*.customization_data' => 'nullable|json',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'customer_notes' => 'nullable|string',
            'internal_comments' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Local Sale Validation Fail (Create)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // 📌 Verificar rol del usuario
        $user = Auth::user();
        if (!$user->profile_id == 1 || !$user->profile_id == 2) {
            return $this->error('No autorizado para crear ventas locales', 403);
        }

        $subtotal = 0;
        $productsData = [];

        foreach ($request->products as $productInput) {
            $product = Product::findOrFail($productInput['product_id']);
            $unitPrice = $productInput['unit_price']; // 📌 asumo que `products` tiene un campo `price`
            $quantity = $productInput['quantity'];
            $lineTotal = $unitPrice * $quantity;

            $subtotal += $lineTotal;

            $productsData[] = [
                'product_id' => $product->id,
                'variant_id' => $productInput['variant_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'comment' => $productInput['comment'] ?? null,
                'customization_data' => $productInput['customization_data'] ?? null,
            ];
        }

        $discountPercent = $request->discount_percent ?? 0;
        $subtotal = (float) $subtotal; // aseguro que sea float

        $discountAmount = ($subtotal * $discountPercent) / 100;
        $discountAmount = round($discountAmount); // 🔥 redondea al entero más cercano

        $total = $subtotal - $discountAmount;
        $total = round($total);

        $sale = Sale::create([
            'client_id' => $request->client_id,
            'channel_id' => 2, // 👈 siempre local comercial
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'payment_method_id' => $request->payment_method_id,
            'customer_notes' => $request->customer_notes,
            'internal_comments' => $request->internal_comments,
            'sale_status_id' => 1, // estado Aprobado
        ]);

        foreach ($productsData as $product) {
            $sale->products()->create($product);
        };
        
        StockService::discountStock($sale);

        // Guardar historial de estado
        SaleStatusHistory::create([
            'sale_id' => $sale->id,
            'sale_status_id' => $sale->sale_status_id,
            'date' => Carbon::now(),
        ]);

        $this->logAudit($user, 'Create Local Sale', $request->all(), $sale);

        return $this->success($sale->load('products.product', 'products.variant'), 'Venta local creada correctamente');
    }

    public function updateLocalSale(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Client Data)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $rules = [
            'products' => 'nullable|array|min:1',
            'products.*.product_id' => 'required_with:products|integer|exists:products,id',
            'products.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'products.*.quantity' => 'required_with:products|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'customer_notes' => 'nullable|string',
            'internal_comments' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Local Sale Validation Fail (Update)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::with(['products.product', 'products.variant'])->findOrFail($id);

        if ($sale->channel_id !== 2) {
            return $this->error('Solo se pueden editar ventas del canal local (channel_id = 2)', 400);
        }

        $user = Auth::user();
        if (!$user->profile_id == 1 || !$user->profile_id == 2) {
            return $this->error('No autorizado para editar ventas locales', 403);
        }

        $subtotal = $sale->subtotal;
        
        StockService::restoreStock($sale);    

        // Si vienen productos, recalcular
        if ($request->has('products')) {
            $subtotal = 0;
            $sale->products()->delete(); // 👈 reemplazo productos anteriores

            foreach ($request->products as $productInput) {
                $product = Product::findOrFail($productInput['product_id']);
                $unitPrice = $productInput['unit_price'];
                $quantity = $productInput['quantity'];
                $lineTotal = $unitPrice * $quantity;

                $subtotal += $lineTotal;

                $sale->products()->create([
                    'product_id' => $product->id,
                    'variant_id' => $productInput['variant_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'comment' => $productInput['comment'] ?? null,
                    'customization_data' => $productInput['customization_data'] ?? null,
                ]);
            };
        }

        $discountPercent = $request->discount_percent ?? $sale->discount_percent ?? 0;
        $discountAmount = ($subtotal * $discountPercent) / 100;
        $total = $subtotal - $discountAmount;

        $discountAmount = round($discountAmount, 2);
        $total = round($total, 2);

        $sale->update([
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'payment_method_id' => $request->payment_method_id ?? $sale->payment_method_id,
            'customer_notes' => $request->customer_notes ?? $sale->customer_notes,
            'internal_comments' => $request->internal_comments ?? $sale->internal_comments,
        ]);

        $sale->load('products.product', 'products.variant');
        
        StockService::discountStock($sale);

        $this->logAudit($user, 'Update Local Sale', $request->all(), $sale);

        return $this->success($sale->load('products.product', 'products.variant'), 'Venta local actualizada correctamente');
    }

    public function generarPdfSale($id)
    {
        try {
            // Cargar la venta con sus productos y variantes
            $sale = Sale::with('products.product', 'products.variant')->findOrFail($id);

            $pdfPaths = [];

            // 🗑️ Eliminar todos los PDFs anteriores de este pedido antes de generar nuevos
            EtiquetaService::limpiarPdfsDelPedido($sale->id, $sale->created_at);
            CintaCoserService::limpiarEtiquetasDeVenta($sale->id, $sale->created_at);
            CintaPlancharService::limpiarEtiquetasDeVenta($sale->id, $sale->created_at);
            BandaService::limpiarBandasDeVenta($sale->id, $sale->created_at);
            SelloService::limpiarSellosDeVenta($sale->id, $sale->created_at);

            foreach ($sale->products as $productOrder) {
                // === 1. Datos base ===
                $customData = json_decode($productOrder->customization_data, true);

                $form = $customData['form'] ?? [];
                $nombreCompleto = trim(($form['name'] ?? '') . ' ' . ($form['lastName'] ?? ''));

                $customColor = $customData['color']['color_code'] ?? null;
                $customIcon = $customData['icon']['icon'] ?? null;

                if ($customIcon && $customData['icon']['name'] == 'Sin dibujo') {
                    $customIcon = null;
                }

                // === SELLOS PERSONALIZADOS (Producto 481) ===
                if (SelloService::esProductoSello($productOrder->product_id)) {
                    try {
                        SelloService::agregarSelloAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $sale->created_at
                        );
                        Log::info("Sello agregado para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando sello", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === BANDAS (Productos 52944 y 52796) ===
                if (BandaService::esProductoBanda($productOrder->product_id)) {
                    try {
                        BandaService::agregarBandaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $sale->created_at
                        );
                        Log::info("Banda agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando banda", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === CINTAS PARA COSER (Producto 1291) ===
                if (CintaCoserService::esProductoCoser($productOrder->product_id)) {
                    try {
                        CintaCoserService::agregarEtiquetaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $sale->created_at
                        );
                        Log::info("Etiqueta de cinta para coser agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando etiqueta de cinta para coser", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                // === CINTAS PARA PLANCHAR (Producto 1247) ===
                if (CintaPlancharService::esProductoPlanchar($productOrder->product_id)) {
                    try {
                        CintaPlancharService::agregarEtiquetaAlPdf(
                            $sale->id,
                            $productOrder,
                            $nombreCompleto,
                            $customColor,
                            $customIcon,
                            $sale->created_at
                        );
                        Log::info("Etiqueta de cinta para planchar agregada para {$nombreCompleto}");
                    } catch (\Throwable $e) {
                        Log::error("Error agregando etiqueta de cinta para planchar", [
                            'error' => $e->getMessage(),
                            'product_order_id' => $productOrder->id,
                        ]);
                    }
                }

                $variant = $productOrder->variant?->variant;
                $productPdf = ProductPdf::where('product_id', $productOrder->product_id)->first();
                Log::info("aquiiiiiiiiiiiiiiiiii");
                // === 2. Si hay un ProductPdf configurado ===
                if ($productPdf) {
                    Log::info($productPdf);

                    $tematicasGuardadas = $productPdf['data']['tematicas'] ?? [];
                    Log::info("Temáticas guardadas en ProductPdf: " . count($tematicasGuardadas));

                    if ($variant) {
                        $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                        if (!$tematicaId) {
                            Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                            continue;
                        }

                        // Buscar la temática correspondiente
                        $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);

                        if ($tematicaCoincidente) {
                            try {
                                $pdfPaths[] = EtiquetaService::generarEtiquetas(
                                    $sale->id,
                                    $tematicaId,
                                    [$nombreCompleto],
                                    $productOrder,
                                    $tematicaCoincidente,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );

                                Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                                continue;
                            } catch (\Throwable $e) {
                                Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                                continue;
                            }
                        }
                    } else {
                        // Sin variant: generar PDF por cada temática guardada
                        foreach ($tematicasGuardadas as $tematica) {
                            $tematicaId = $tematica['id'] ?? null;

                            try {
                                $pdfPaths[] = EtiquetaService::generarEtiquetas(
                                    $sale->id,
                                    $tematicaId,
                                    [$nombreCompleto],
                                    $productOrder,
                                    $tematica,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );

                                Log::info("PDF generado sin variante para {$nombreCompleto}, temática ID: {$tematicaId}");
                            } catch (\Throwable $e) {
                                Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                            }
                        }
                    }
                } else {
                    Log::info(message: "Sin informacion del pdf en el producto con id: $productOrder->product_id");
                }
            }

            return $this->success(
                $sale->load('products.product', 'products.variant'),
                'PDF generado correctamente',
                ['pdf_paths' => $pdfPaths]
            );

        } catch (\Throwable $th) {
            Log::error('Error al generar PDF: ' . $th->getMessage());
            return $this->error('Error al generar PDF', 500);
        }
    }

    public function generateBulkPdfs(Request $request)
    {
        $rules = [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk PDF Generation Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        try {
            $fromDate = Carbon::parse($request->from_date)->startOfDay();
            $toDate = Carbon::parse($request->to_date)->endOfDay();

            // Buscar ventas aprobadas (sale_status_id = 1) y en producción (sale_status_id = 2) en el rango de fechas
            $sales = Sale::with('products.product', 'products.variant')
                ->whereIn('sale_status_id', [1, 2])
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->get();

            if ($sales->isEmpty()) {
                return $this->success([], 'No se encontraron ventas aprobadas en el rango de fechas especificado', [
                    'total_sales' => 0,
                    'from_date' => $fromDate->format('Y-m-d'),
                    'to_date' => $toDate->format('Y-m-d')
                ]);
            }

            $results = [];
            $totalPdfsGenerated = 0;
            $salesWithErrors = [];

            foreach ($sales as $sale) {
                $salePdfPaths = [];

                try {
                    // 🗑️ Eliminar todos los PDFs anteriores de este pedido antes de generar nuevos
                    EtiquetaService::limpiarPdfsDelPedido($sale->id, $sale->created_at);
                    CintaCoserService::limpiarEtiquetasDeVenta($sale->id, $sale->created_at);
                    CintaPlancharService::limpiarEtiquetasDeVenta($sale->id, $sale->created_at);
                    BandaService::limpiarBandasDeVenta($sale->id, $sale->created_at);
                    SelloService::limpiarSellosDeVenta($sale->id, $sale->created_at);

                    foreach ($sale->products as $productOrder) {
                        // === 1. Datos base ===
                        $customData = json_decode($productOrder->customization_data, true);

                        $form = $customData['form'] ?? [];
                        $nombreCompleto = trim(($form['name'] ?? '') . ' ' . ($form['lastName'] ?? ''));

                        $customColor = $customData['color']['color_code'] ?? null;
                        $customIcon = $customData['icon']['icon'] ?? null;

                        if ($customIcon && $customData['icon']['name'] == 'Sin dibujo') {
                            $customIcon = null;
                        }

                        // === SELLOS PERSONALIZADOS (Producto 481) ===
                        if (SelloService::esProductoSello($productOrder->product_id)) {
                            try {
                                SelloService::agregarSelloAlPdf(
                                    $sale->id,
                                    $productOrder,
                                    $nombreCompleto,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );
                                Log::info("Sello agregado para {$nombreCompleto}");
                            } catch (\Throwable $e) {
                                Log::error("Error agregando sello", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                            }
                        }

                        // === BANDAS (Productos 52944 y 52796) ===
                        if (BandaService::esProductoBanda($productOrder->product_id)) {
                            try {
                                BandaService::agregarBandaAlPdf(
                                    $sale->id,
                                    $productOrder,
                                    $nombreCompleto,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );
                                Log::info("Banda agregada para {$nombreCompleto}");
                            } catch (\Throwable $e) {
                                Log::error("Error agregando banda", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                            }
                        }

                        // === CINTAS PARA COSER (Producto 1291) ===
                        if (CintaCoserService::esProductoCoser($productOrder->product_id)) {
                            try {
                                CintaCoserService::agregarEtiquetaAlPdf(
                                    $sale->id,
                                    $productOrder,
                                    $nombreCompleto,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );
                                Log::info("Etiqueta de cinta para coser agregada para {$nombreCompleto}");
                            } catch (\Throwable $e) {
                                Log::error("Error agregando etiqueta de cinta para coser", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                            }
                        }

                        // === CINTAS PARA PLANCHAR (Producto 1247) ===
                        if (CintaPlancharService::esProductoPlanchar($productOrder->product_id)) {
                            try {
                                CintaPlancharService::agregarEtiquetaAlPdf(
                                    $sale->id,
                                    $productOrder,
                                    $nombreCompleto,
                                    $customColor,
                                    $customIcon,
                                    $sale->created_at
                                );
                                Log::info("Etiqueta de cinta para planchar agregada para {$nombreCompleto}");
                            } catch (\Throwable $e) {
                                Log::error("Error agregando etiqueta de cinta para planchar", [
                                    'error' => $e->getMessage(),
                                    'product_order_id' => $productOrder->id,
                                ]);
                            }
                        }

                        $variant = $productOrder->variant?->variant;
                        $productPdf = ProductPdf::where('product_id', $productOrder->product_id)->first();

                        // === 2. Si hay un ProductPdf configurado ===
                        if ($productPdf) {
                            $tematicasGuardadas = $productPdf['data']['tematicas'] ?? [];

                            if ($variant) {
                                $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                                if (!$tematicaId) {
                                    Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                                    continue;
                                }

                                // Buscar la temática correspondiente
                                $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);

                                if ($tematicaCoincidente) {
                                    try {
                                        $pdfPath = EtiquetaService::generarEtiquetas(
                                            $sale->id,
                                            $tematicaId,
                                            [$nombreCompleto],
                                            $productOrder,
                                            $tematicaCoincidente,
                                            $customColor,
                                            $customIcon,
                                            $sale->created_at
                                        );

                                        $salePdfPaths[] = $pdfPath;
                                        Log::info("PDF generado para venta {$sale->id}, {$nombreCompleto}, temática ID: {$tematicaId}");
                                        continue;
                                    } catch (\Throwable $e) {
                                        Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                            'error' => $e->getMessage(),
                                            'product_order_id' => $productOrder->id,
                                        ]);
                                        continue;
                                    }
                                }
                            } else {
                                // Sin variant: generar PDF por cada temática guardada
                                foreach ($tematicasGuardadas as $tematica) {
                                    $tematicaId = $tematica['id'] ?? null;

                                    try {
                                        $pdfPath = EtiquetaService::generarEtiquetas(
                                            $sale->id,
                                            $tematicaId,
                                            [$nombreCompleto],
                                            $productOrder,
                                            $tematica,
                                            $customColor,
                                            $customIcon,
                                            $sale->created_at
                                        );

                                        $salePdfPaths[] = $pdfPath;
                                        Log::info("PDF generado sin variante para venta {$sale->id}, {$nombreCompleto}, temática ID: {$tematicaId}");
                                    } catch (\Throwable $e) {
                                        Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                                            'error' => $e->getMessage(),
                                            'product_order_id' => $productOrder->id,
                                        ]);
                                    }
                                }
                            }
                        }

                        Log::info(message: "Sin informacion del pdf en el producto con id: $productOrder->product_id");

                        continue;
                    }

                    $totalPdfsGenerated += count($salePdfPaths);

                    $results[] = [
                        'sale_id' => $sale->id,
                        'pdfs_generated' => count($salePdfPaths),
                        'status' => 'success'
                    ];

                } catch (\Throwable $e) {
                    Log::error("Error al procesar venta {$sale->id}: " . $e->getMessage());
                    $salesWithErrors[] = [
                        'sale_id' => $sale->id,
                        'error' => $e->getMessage()
                    ];

                    $results[] = [
                        'sale_id' => $sale->id,
                        'pdfs_generated' => 0,
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            $this->logAudit(
                Auth::user(),
                'Bulk PDF Generation',
                $request->all(),
                [
                    'total_sales_processed' => $sales->count(),
                    'total_pdfs_generated' => $totalPdfsGenerated,
                    'sales_with_errors' => count($salesWithErrors)
                ]
            );

            return $this->success(
                $results,
                'Proceso de generación masiva de PDFs completado',
                [
                    'total_sales_processed' => $sales->count(),
                    'total_pdfs_generated' => $totalPdfsGenerated,
                    'sales_with_errors' => count($salesWithErrors),
                    'from_date' => $fromDate->format('Y-m-d'),
                    'to_date' => $toDate->format('Y-m-d')
                ]
            );

        } catch (\Throwable $th) {
            Log::error('Error en generación masiva de PDFs: ' . $th->getMessage());
            $this->logAudit(Auth::user(), 'Bulk PDF Generation Error', $request->all(), $th->getMessage());
            return $this->error('Error al generar PDFs masivamente: ' . $th->getMessage(), 500);
        }
    }

    public function allSaleStatus()
    {
        $statuses = SaleStatus::all();
        return $this->success($statuses, 'Estados de venta obtenidos correctamente');
    }

    public function allPaymentMethod()
    {
        $payment = PaymentMethod::all();

        $this->logAudit(null, 'Payment Method', $payment, $payment);

        return $this->success($payment->load('status'), 'Metodos de pago obtenidos correctamente');
    }

    public function allChannelSale()
    {
        $channel = Channel::all();

        $this->logAudit(null, 'Channel sale', $channel, $channel);

        return $this->success($channel, 'Canales de ventas obtenidos correctamente');
    }

    public function exportExcel(Request $request)
    {
        $from = $request->query('start_date') . ' 00:00:00';
        $to = $request->query('end_date') . ' 23:59:59';

        $salesExport = new SalesExport($from, $to);

        // Guardar en storage/app/exports
        $fileName = 'sales_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'exports/' . $fileName;

        Excel::store($salesExport, $filePath, 'local'); // 'local' = storage/app

        // Luego, si querés devolverlo para descargar:
        return Excel::download($salesExport, $fileName);
    }

    public function exportWholesaleExcel(Request $request)
    {
        $from = ($request->query('start_date') ?? '2024-12-01') . ' 00:00:00';
        $to = ($request->query('end_date') ?? now()->format('Y-m-d')) . ' 23:59:59';

        $export = new WholesaleSalesExport($from, $to);
        $fileName = 'compras-mayoristas_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $fileName);
    }

    /**
     * Obtener estadísticas para el dashboard
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Parámetros:
     * - from_date: Fecha desde (requerido)
     * - to_date: Fecha hasta (requerido)
     * - channel_id: ID del canal de venta (opcional, si no se envía o es 'all' trae todos los canales)
     * - product_id: ID del producto (opcional, filtra ventas que contengan este producto)
     */
    /**
     * Asociar una venta a otra manualmente desde el admin
     *
     * @param Request $request
     * @param int $id ID de la venta que se quiere asociar
     * @return \Illuminate\Http\JsonResponse
     */
    public function associateSale(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Associate Sale)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $rules = [
            'parent_sale_id' => 'required|integer|exists:sales,id',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Associate Sale)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $sale = Sale::findOrFail($id);
        $parentSaleId = $request->parent_sale_id;

        // Validar que no esté intentando asociar una venta consigo misma
        if ($sale->id == $parentSaleId) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Associate Sale)', $request->all(), 'No puedes asociar una venta consigo misma');
            return $this->error('No puedes asociar una venta consigo misma', 400);
        }

        // Validar que la venta padre exista
        $parentSale = Sale::find($parentSaleId);
        if (!$parentSale) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Associate Sale)', $request->all(), 'La venta padre no existe');
            return $this->error('El número de pedido especificado no existe', 404);
        }

        // Validar que no se cree una asociación circular
        // (verificar que la venta padre no sea hija de la venta actual)
        if ($parentSale->sale_id == $sale->id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Associate Sale)', $request->all(), 'No se puede crear una asociación circular');
            return $this->error('No se puede asociar: esto crearía una asociación circular', 400);
        }

        $sale->sale_id = $parentSaleId;
        $sale->save();

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'shippingMethod', 'coupons', 'user', 'childSales', 'parentSale']);

        $this->logAudit(Auth::user(), 'Associate Sale', ['sale_id' => $id, 'parent_sale_id' => $parentSaleId], $sale);

        return $this->success($sale, 'Venta asociada correctamente');
    }

    /**
     * Remover la asociación de una venta
     *
     * @param Request $request
     * @param int $id ID de la venta cuya asociación se quiere remover
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAssociation(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Remove Association)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

        $sale = Sale::findOrFail($id);

        // Validar que la venta tenga una asociación
        if (!$sale->sale_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Remove Association)', ['sale_id' => $id], 'Esta venta no tiene una asociación');
            return $this->error('Esta venta no tiene una asociación para remover', 400);
        }

        $oldParentSaleId = $sale->sale_id;
        $sale->sale_id = null;
        $sale->save();

        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'shippingMethod', 'coupons', 'user', 'childSales', 'parentSale']);

        $this->logAudit(Auth::user(), 'Remove Sale Association', ['sale_id' => $id, 'old_parent_sale_id' => $oldParentSaleId], $sale);

        return $this->success($sale, 'Asociación removida correctamente');
    }

    public function getDashboardStats(Request $request)
    {
        try {
            // Validar parámetros requeridos
            if (!$request->has('from_date') || !$request->has('to_date')) {
                return $this->error('Los parámetros from_date y to_date son requeridos', 400);
            }

            // Parsear fechas desde zona horaria Argentina a UTC
            $fromDate = Carbon::parse($request->query('from_date'), 'America/Argentina/Buenos_Aires')
                ->startOfDay()
                ->setTimezone('UTC');

            $toDate = Carbon::parse($request->query('to_date'), 'America/Argentina/Buenos_Aires')
                ->endOfDay()
                ->setTimezone('UTC');

            // Construir query base: ventas pagadas (no incluir estados 8 y 9) filtradas por fecha de aprobación
            $query = Sale::with([
                'products.product',
                'products.variant',
                'client',
                'paymentMethod',
                'channel'
            ])
            ->whereNotIn('sale_status_id', [8, 9]); // Solo ventas pagadas

            // Filtrar por fecha de aprobación (estado_id = 1 en sales_status_history)
            $query->whereHas('statusHistory', function ($q) use ($fromDate, $toDate) {
                $q->where('sale_status_id', 1)
                  ->whereBetween('date', [$fromDate, $toDate]);
            });

            // Filtrar por canal si se especifica y no es 'all'
            $channelId = $request->query('channel_id');
            if ($channelId && $channelId !== 'all') {
                $query->where('channel_id', $channelId);
            }

            // Filtrar por producto si se especifica
            $productId = $request->query('product_id');
            if ($productId) {
                $query->whereHas('products', function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }

            // Obtener todas las ventas
            $sales = $query->get();

            // 🔹 INDICADORES
            // Si hay filtro de producto, calcular indicadores solo para ese producto
            if ($productId) {
                $totalSales = 0;
                $totalDiscounts = 0;
                $totalProducts = 0;

                foreach ($sales as $sale) {
                    foreach ($sale->products as $saleProduct) {
                        if ($saleProduct->product_id == $productId) {
                            $lineTotal = floatval($saleProduct->quantity) * floatval($saleProduct->unit_price);

                            // Calcular el descuento proporcional para este producto
                            $saleTotal = floatval($sale->total);
                            $saleDiscount = floatval($sale->discount_amount ?? 0);
                            $discountProportion = $saleTotal > 0 ? ($saleDiscount / $saleTotal) : 0;
                            $lineDiscount = $lineTotal * $discountProportion;

                            $totalSales += $lineTotal;
                            $totalDiscounts += $lineDiscount;
                            $totalProducts += $saleProduct->quantity;
                        }
                    }
                }

                $netSales = $totalSales - $totalDiscounts;
                $ordersCount = $sales->count();
            } else {
                // Sin filtro de producto, calcular totales generales
                $totalSales = $sales->sum('total');
                $totalDiscounts = $sales->sum('discount_amount');
                $netSales = $totalSales - $totalDiscounts;
                $ordersCount = $sales->count();

                // Contar total de productos vendidos
                $totalProducts = 0;
                foreach ($sales as $sale) {
                    foreach ($sale->products as $product) {
                        $totalProducts += $product->quantity;
                    }
                }
            }

            // 🔹 PRODUCTOS VENDIDOS (agrupados con estadísticas)
            $productsStats = [];
            foreach ($sales as $sale) {
                foreach ($sale->products as $saleProduct) {
                    // Si hay filtro de producto, solo incluir ese producto
                    if ($productId && $saleProduct->product_id != $productId) {
                        continue;
                    }

                    // Verificar que el producto existe
                    if (!$saleProduct->product) {
                        continue;
                    }

                    // Obtener el nombre del producto de forma segura
                    $productName = is_string($saleProduct->product->name)
                        ? $saleProduct->product->name
                        : 'Producto desconocido';

                    // Agregar variante al nombre si existe
                    if ($saleProduct->variant && isset($saleProduct->variant->variant)) {
                        $variantName = is_string($saleProduct->variant->variant)
                            ? $saleProduct->variant->variant
                            : '';
                        if ($variantName) {
                            $productName .= ' - ' . $variantName;
                        }
                    }

                    if (!isset($productsStats[$productName])) {
                        $productsStats[$productName] = [
                            'product' => $productName,
                            'product_sales_cant' => 0,
                            'net_sales' => 0.0,
                            'quantity' => 0
                        ];
                    }

                    $lineTotal = floatval($saleProduct->quantity) * floatval($saleProduct->unit_price);

                    // Calcular el descuento proporcional para este producto
                    $saleTotal = floatval($sale->total);
                    $saleDiscount = floatval($sale->discount_amount ?? 0);
                    $discountProportion = $saleTotal > 0 ? ($saleDiscount / $saleTotal) : 0;
                    $lineDiscount = $lineTotal * $discountProportion;
                    $lineNetSales = $lineTotal - $lineDiscount;

                    $productsStats[$productName]['product_sales_cant'] += intval($saleProduct->quantity);
                    $productsStats[$productName]['net_sales'] += $lineNetSales;
                    $productsStats[$productName]['quantity'] += intval($saleProduct->quantity);
                }
            }

            // Convertir a array y ordenar por ventas netas (mayor a menor)
            $productsSold = array_values($productsStats);
            usort($productsSold, function($a, $b) {
                return $b['net_sales'] <=> $a['net_sales'];
            });

            // Eliminar el campo 'quantity' y asegurar que los valores sean numéricos
            $productsSold = array_map(function($item) {
                return [
                    'product' => $item['product'],
                    'product_sales_cant' => (int) $item['product_sales_cant'],
                    'net_sales' => round((float) $item['net_sales'], 2)
                ];
            }, $productsSold);

            // Producto más vendido
            $bestSellingProduct = 'N/A';
            $bestSellingProductTotal = 0;
            if (count($productsSold) > 0) {
                $bestSellingProduct = $productsSold[0]['product'];
                $bestSellingProductTotal = $productsSold[0]['net_sales'];
            }

            // 🔹 LISTADO DE VENTAS
            $salesList = [];
            foreach ($sales as $sale) {
                // Obtener la fecha de aprobación desde el historial
                $approvalDate = $sale->statusHistory()
                    ->where('sale_status_id', 1)
                    ->orderBy('date', 'asc')
                    ->first();

                $salesList[] = [
                    'id' => $sale->id,
                    'date' => $approvalDate ? Carbon::parse($approvalDate->date)
                        ->setTimezone('America/Argentina/Buenos_Aires')
                        ->format('Y-m-d H:i:s') : $sale->created_at->format('Y-m-d H:i:s'),
                    'email' => $sale->client ? ($sale->client->email ?? 'N/A') : 'N/A',
                    'total' => round(floatval($sale->total), 2),
                    'payment_method' => [
                        'id' => $sale->paymentMethod ? ($sale->paymentMethod->id ?? null) : null,
                        'name' => $sale->paymentMethod ? ($sale->paymentMethod->name ?? 'N/A') : 'N/A'
                    ]
                ];
            }

            // 🔹 CONSTRUIR RESPUESTA
            $response = [
                'indicators' => [
                    [
                        'name' => 'total_sales',
                        'value' => round($totalSales, 2)
                    ],
                    [
                        'name' => 'net_sales',
                        'value' => round($netSales, 2)
                    ],
                    [
                        'name' => 'orders',
                        'value' => $ordersCount
                    ],
                    [
                        'name' => 'cant_products',
                        'value' => $totalProducts
                    ],
                    [
                        'name' => 'best_selling_product',
                        'value' => $bestSellingProduct
                    ],
                    [
                        'name' => 'best_selling_product_total',
                        'value' => round($bestSellingProductTotal, 2)
                    ]
                ],
                'products_sold' => $productsSold,
                'sales' => $salesList
            ];

            return $this->success($response, 'Estadísticas obtenidas correctamente');

        } catch (\Throwable $th) {
            Log::error('Error al obtener estadísticas del dashboard: ' . $th->getMessage());
            $this->logAudit(Auth::user(), 'Dashboard Stats Error', $request->all(), $th->getMessage());
            return $this->error('Error al obtener estadísticas: ' . $th->getMessage(), 500);
        }
    }

    private function sendMetaCapiPurchaseEvent(Sale $sale): void
    {
        $pixelId = config('services.meta.pixel_id');
        $capiToken = config('services.meta.capi_token');
        $capiLog = Log::channel('meta_capi');

        if (empty($pixelId) || empty($capiToken) || !app()->isProduction()) {
            $capiLog->info('Evento omitido: no configurado o entorno no productivo', ['sale_id' => $sale->id, 'env' => app()->environment()]);
            return;
        }

        try {
            $sale->loadMissing('client');

            $total = (float) ($sale->total ?? 0);

            $emailRaw = strtolower(trim($sale->client->email ?? ''));

            $eventData = [
                'data' => [
                    [
                        'event_name' => 'Purchase',
                        'event_time' => now()->timestamp,
                        'event_id' => 'sale_' . $sale->id,
                        'action_source' => 'website',
                        'user_data' => [
                            'em' => !empty($emailRaw) ? [hash('sha256', $emailRaw)] : [],
                        ],
                        'custom_data' => [
                            'currency' => 'ARS',
                            'value' => round((float) $total, 2),
                            'order_id' => (string) $sale->id,
                        ],
                    ],
                ],
            ];

            $capiLog->info('Enviando evento Purchase a Meta CAPI', [
                'sale_id'       => $sale->id,
                'pixel_id'      => $pixelId,
                'email_raw'     => $emailRaw,
                'total'         => round((float) $total, 2),
                'payload'       => $eventData,
            ]);

            $response = Http::withToken($capiToken)
                ->post("https://graph.facebook.com/v19.0/{$pixelId}/events", $eventData);

            if ($response->successful()) {
                $capiLog->info('Evento Purchase enviado correctamente', [
                    'sale_id'       => $sale->id,
                    'http_status'   => $response->status(),
                    'response'      => $response->json(),
                ]);
            } else {
                $capiLog->warning('Respuesta no exitosa de Meta CAPI', [
                    'sale_id'       => $sale->id,
                    'http_status'   => $response->status(),
                    'body'          => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            $capiLog->error('Error enviando evento Purchase', [
                'sale_id' => $sale->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}