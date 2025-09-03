<?php

namespace App\Http\Controllers;

use App\Mail\NewClientForSale;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Coupon;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SaleStatusHistory;
use App\Traits\ApiResponse;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
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
                'client:id,name,email',
                'channel:id,name',
                'products.product',
                'products.variant',
                'status:id,name',
                'statusHistory'
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
            $this->logAudit(Auth::user(), 'Get Sales List', $request->all(), collect($sales->items())->take(10));
            return $this->success($sales, 'Ventas obtenidas');
        }

        // Paginación
        $sales = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Sales List', $request->all(), collect($sales->items())->take(10));

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
        $sale->load(['client', 'channel', 'products.product', 'products.variant', 'status', 'statusHistory'])
            ->findOrFail($id);

        $this->logAudit(Auth::user(), 'Get Sale Detail', ['id' => $id], $sale);
        return $this->success($sale, 'Venta obtenida correctamente');

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
            'shipping_address' => 'required|string|max:255',
            'shipping_locality_id' => 'required|integer|exists:localities,id',
            'shipping_postal_code' => 'required|string|max:20',
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
                ]);
            }

            $this->logAudit(null, 'Store Client Sale', $request->all(), $client);
        }

        // agregamos el client_id al request para crear la venta
        $request->merge(['client_id' => $client->id]);

        $sale = Sale::create([
            'client_id' => $client->id,
            'channel_id' => $request->channel_id,
            'external_id' => $request->external_id,
            'address' => $request->shipping_address, // 👈 se llena
            'locality_id' => $request->shipping_locality_id,
            'postal_code' => $request->shipping_postal_code,
            'client_shipping_id' => $request->client_shipping_id,
            'subtotal' => $request->subtotal,
            'shipping_cost' => $request->shipping_cost,
            'shipping_method_id' => $request->shipping_method_id,
            'customer_notes' => $request->customer_notes,
            'internal_comments' => $request->internal_comments,
            'sale_status_id' => $request->sale_status_id,
            'sale_id' => $request->sale_id,
        ]);

        if ($request->coupon_code) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();
            if ($coupon) {
                $sale->coupon_id = $coupon->id;
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

        return response()->json([
            'message' => 'Venta creada correctamente',
            'data' => $sale->load('products.variant')
        ], 201);
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

        $sale = Sale::findOrFail($id);
        $sale->update($request->only(array_keys($rules)));

        return response()->json([
            'message' => 'Venta actualizada correctamente',
            'data' => $sale
        ]);
    }

    // 📌 Eliminar venta
    public function destroy($id)
    {
        $sale = Sale::findOrFail($id);
        $sale->delete();

        return response()->json(['message' => 'Venta eliminada correctamente']);
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

        return response()->json([
            'message' => 'Estado de venta actualizado correctamente',
            'data' => $sale
        ]);
    }
}
