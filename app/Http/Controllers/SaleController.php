<?php

namespace App\Http\Controllers;

use App\Mail\NewClientForSale;
use App\Mail\OrderSummaryMail;
use App\Mail\OrderSummaryMailTo;
use App\Mail\OrderProductionsMail;
use App\Mail\OrderSendMail;
use App\Mail\OrderRetiredMail;
use App\Mail\OrderWithdrawMail;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SaleStatus;
use App\Models\SaleStatusHistory;
use App\Services\EtiquetaService;
use App\Traits\ApiResponse;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $query = Sale::query()
            ->with([
                'client',
                'channel:id,name',
                'products.product',
                'products.variant',
                'status:id,name',
                'statusHistory',
                'shippingMethod',
                'coupon'
            ])
            ->orderBy('created_at', 'desc');

        // Filtros opcionales
        if ($request->has('client_id')) {
            $query->where('client_id', $request->query('client_id'));
        }

        if ($request->has('sale_status_id')) {
            $query->where('sale_status_id', $request->query('sale_status_id'));
        }

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('products.product', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Si no hay perPage, traer todo
        if (!$perPage) {
            $sales = $query->get();
            $this->logAudit(Auth::user(), 'Get Sales List', $request->all(), $sales->take(1));
            return $this->success($sales, 'Ventas obtenidas');
        }

        // Paginación
        $sales = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Sales List', $request->all(), collect($sales->items())->take(1));

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
        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory', 'shippingMethod', 'coupon', 'user'])
            ->findOrFail($id);

        $this->logAudit(Auth::user(), 'Get Sale Detail', ['id' => $id], $sale);
        return $this->success($sale, 'Venta obtenida correctamente');

    }

    public function showRecort($id)
    {
        $sale = $this->findObject(Sale::class, $id);

        if (!$sale) {
            return $this->error('Venta no encontrada', 404);
        }

        // Cargar solo las relaciones necesarias
        $sale->load(['products.product.images', 'products.variant', 'status', 'coupon']);

        // Preparar la respuesta resumida
        $data = [
            'id' => $sale->id,
            'subtotal' => $sale->subtotal,
            'total' => $sale->total,
            'shipping_cost' => $sale->shipping_cost,
            'shipping_method_id' => $sale->shipping_method_id,
            'payment_method_id' => $sale->payment_method_id,
            'discount_amount' => $sale->discount_amount,
            'status' => $sale->status, // puedes ajustar si quieres solo el nombre o todo el objeto
            'products' => $sale->products,
            'coupon' => $sale->coupon
        ];

        $this->logAudit(null, 'Get Sale Summary', ['id' => $id], $data);

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
                'name' => $client->name,
                'password' => $randomPassword,
            ];

            Mail::to(users: $request->client_mail)->send(new NewClientForSale($mailData));


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

        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();
            if ($coupon) {
                $sale->coupon_id = $coupon->id;
                $sale->discount_amount = $request->discount_amount;
                $sale->save();
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

        $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);

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

        $sale->load('products.variant');

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

        if ($sale->sale_status_id == 3 && $sale->payment_method_id != 1) { // estado "enviado"
            Mail::to($sale->client->email)->send(new OrderSendMail($sale));
        }

        if ($sale->sale_status_id == 3 && $sale->payment_method_id == 1) { // estado "enviado"
            Mail::to($sale->client->email)->send(new OrderWithdrawMail($sale));
        }

        if ($sale->sale_status_id == 4) { // estado "Retirado"
            Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
        }

        if ($sale->sale_status_id == 1) {
            $notifyEmail = env('MAIL_NOTIFICATION_TO');

            Mail::to($sale->client->email)->send(new OrderSummaryMail($sale));
            Mail::to($notifyEmail)->send(new OrderSummaryMailTo($sale));

            foreach ($sale->products as $productOrder) {
                // Obtener nombre completo desde customization_data
                $customData = json_decode($productOrder->customization_data, true);
                $nombreCompleto = trim(($customData['form']['name'] ?? '') . ' ' . ($customData['form']['lastName'] ?? ''));

                // Acceso seguro al Variant real
                $variant = $productOrder->variant?->variant;

                if (!$variant) {
                    Log::warning("No se encontró variante para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    continue; // saltar a siguiente producto
                }

                // Obtener temática de manera segura
                $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                if (!$tematicaId) {
                    Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    continue; // saltar a siguiente producto
                }

                // Generar PDF dentro de su propio try/catch para que no detenga el bucle
                try {
                    $pdfPath = EtiquetaService::generarEtiquetas($sale->id, $tematicaId, [$nombreCompleto], $productOrder);
                    $pdfPaths[] = $pdfPath;
                    Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                } catch (\Throwable $e) {
                    Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                    // continuar con el siguiente producto
                }
            }
        }

        $this->logAudit(Auth::user() ?? null, 'Update Status Sale', $request->all(), $sale);
        return $this->success($sale, 'Estado de venta actualizada correctamente');
    }

    public function changeStatusAdmin(Request $request, $id)
    {
        $user = Auth::user();
        $sale = Sale::findOrFail($id);
        $sale->sale_status_id = $request->sale_status_id;
        $sale->save();

        if (!$user->profile_id) {
            $this->logAudit(Auth::user(), 'Sale Validation Fail (Update Status)', $request->all(), 'No tienes los permisos necesarios');
            return $this->error('No tienes los permisos necesarios', 401);
        }

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

        if ($sale->sale_status_id == 3 && $sale->payment_method_id != 1) { // estado "enviado"
            Mail::to($sale->client->email)->send(new OrderSendMail($sale));
        }

        if ($sale->sale_status_id == 3 && $sale->payment_method_id == 1) { // estado "enviado"
            Mail::to($sale->client->email)->send(new OrderWithdrawMail($sale));
        }

        if ($sale->sale_status_id == 4) { // estado "Retirado"
            Mail::to($sale->client->email)->send(new OrderRetiredMail($sale));
        }

        if ($sale->sale_status_id == 1) {
            foreach ($sale->products as $productOrder) {
                // Obtener nombre completo desde customization_data
                $customData = json_decode($productOrder->customization_data, true);
                $nombreCompleto = trim(($customData['Nombre'] ?? '') . ' ' . ($customData['Apellido'] ?? ''));

                // Acceso seguro al Variant real
                $variant = $productOrder->variant?->variant;

                if (!$variant) {
                    Log::warning("No se encontró variante para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    continue; // saltar a siguiente producto
                }

                // Obtener temática de manera segura
                $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                if (!$tematicaId) {
                    Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    continue; // saltar a siguiente producto
                }

                // Generar PDF dentro de su propio try/catch para que no detenga el bucle
                try {
                    $pdfPath = EtiquetaService::generarEtiquetas($sale->id, $tematicaId, [$nombreCompleto], $productOrder);
                    $pdfPaths[] = $pdfPath;
                    Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                } catch (\Throwable $e) {
                    Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                    // continuar con el siguiente producto
                }
            }
        }

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
            $unitPrice = $product->price; // 📌 asumo que `products` tiene un campo `price`
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
            'sale_status_id' => 7, // 📌 asumo "pendiente" o inicial
        ]);

        foreach ($productsData as $product) {
            $sale->products()->create($product);
        }

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

        $sale = Sale::findOrFail($id);

        if ($sale->channel_id !== 2) {
            return $this->error('Solo se pueden editar ventas del canal local (channel_id = 2)', 400);
        }

        $user = Auth::user();
        if (!$user->profile_id == 1 || !$user->profile_id == 2) {
            return $this->error('No autorizado para editar ventas locales', 403);
        }

        $subtotal = $sale->subtotal;

        // Si vienen productos, recalcular
        if ($request->has('products')) {
            $subtotal = 0;
            $sale->products()->delete(); // 👈 reemplazo productos anteriores

            foreach ($request->products as $productInput) {
                $product = Product::findOrFail($productInput['product_id']);
                $unitPrice = $product->price;
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
            }
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

        $this->logAudit($user, 'Update Local Sale', $request->all(), $sale);

        return $this->success($sale->load('products.product', 'products.variant'), 'Venta local actualizada correctamente');
    }

    public function generarPdfSale($id)
    {
        try {
            // Cargar la venta con sus productos y variantes
            $sale = Sale::with('products.product', 'products.variant')->findOrFail($id);

            $pdfPaths = [];

            foreach ($sale->products as $productOrder) {
                // Obtener nombre completo desde customization_data
                $customData = json_decode($productOrder->customization_data, true);
                $nombreCompleto = trim(($customData['form']['name'] ?? '') . ' ' . ($customData['form']['lastName'] ?? ''));

                // Acceso seguro al Variant real
                $variant = $productOrder->variant?->variant;

                if (!$variant) {
                    Log::warning("No se encontró variante para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    continue; // saltar a siguiente producto
                }

                // Obtener temática de manera segura
                $tematicaId = $variant['attributesvalues'][0]['id'] ?? null;

                if (!$tematicaId) {
                    Log::warning("No se encontró temática para {$nombreCompleto}, product_order ID: {$productOrder->id}");
                    continue; // saltar a siguiente producto
                }

                // Generar PDF dentro de su propio try/catch para que no detenga el bucle
                try {
                    $pdfPath = EtiquetaService::generarEtiquetas($sale->id, $tematicaId, [$nombreCompleto], $productOrder);
                    $pdfPaths[] = $pdfPath;
                    Log::info("PDF generado para {$nombreCompleto}, temática ID: {$tematicaId}");
                } catch (\Throwable $e) {
                    Log::error("Error generando PDF para {$nombreCompleto}, temática ID: {$tematicaId}", [
                        'error' => $e->getMessage(),
                        'product_order_id' => $productOrder->id,
                    ]);
                    // continuar con el siguiente producto
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

    public function allSaleStatus()
    {
        $statuses = SaleStatus::all();
        return $this->success($statuses, 'Estados de venta obtenidos correctamente');
    }
}
