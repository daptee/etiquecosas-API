<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class CouponController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
{
    $perPage = $request->query('quantity');
    $page = $request->query('page', 1);
    $search = $request->query('search');
    $statusId = $request->query('status');

    $query = Coupon::query()
        ->select(
            'id',
            'name',
            'code',
            'date_from',
            'date_to',
            'min_amount',
            'type',
            'applies_to_shipping',
            'max_use_per_user',
            'max_use_per_code',
            'coupon_status_id',
            'applies_to_all_products',
            'value'
        )
        ->with('status', 'categories:id', 'products:id');

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        });
    }

    if ($statusId) {
        $query->where('coupon_status_id', $statusId);
    }

    $query->orderBy('name', 'asc');

    if (!$perPage) {
        $coupons = $query->get();
        $this->logAudit(Auth::user(), 'Get Coupons List', $request->all(), $coupons);
        return $this->success($coupons, 'Cupones obtenidos');
    }

    $coupons = $query->paginate($perPage, ['*'], 'page', $page);
    $this->logAudit(Auth::user(), 'Get Coupons List', $request->all(), $coupons);

    $metaData = [
        'current_page' => $coupons->currentPage(),
        'last_page' => $coupons->lastPage(),
        'per_page' => $coupons->perPage(),
        'total' => $coupons->total(),
        'from' => $coupons->firstItem(),
        'to' => $coupons->lastItem(),
    ];

    return $this->success($coupons->items(), 'Cupones obtenidos', $metaData);
}

public function show($id)
{
    $coupon = $this->findObject(Coupon::class, $id, [
        'id',
        'name',
        'code',
        'date_from',
        'date_to',
        'min_amount',
        'type',
        'applies_to_shipping',
        'max_use_per_user',
        'max_use_per_code',
        'coupon_status_id',
        'applies_to_all_products',
        'value'
    ]);

    $coupon->load('categories:id', 'products:id');
    $this->logAudit(Auth::user(), 'Get Coupon Details', $id, $coupon);

    return $this->success($coupon, 'Cupon obtenido');
}

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:255|unique:coupons,code',
        'date_from' => 'required|date',
        'date_to' => 'required|date|after_or_equal:date_from',
        'min_amount' => 'required|numeric|min:0',
        'type' => 'required|in:Fijo,Porcentaje',
        'applies_to_shipping' => 'boolean',
        'max_use_per_user' => 'required|integer|min:0',
        'max_use_per_code' => 'required|integer|min:0',
        'coupon_status_id' => 'required|exists:coupon_statuses,id',
        'categories' => 'nullable|array',
        'categories.*' => 'integer|exists:categories,id',
        'products' => 'nullable|array',
        'products.*' => [
            'integer',
            'distinct',
            Rule::in(Product::pluck('id')->toArray()),
        ],
        'products_all' => 'nullable|boolean',
        'value' => 'required|numeric|min:0',
    ]);

    if ($validator->fails()) {
        $this->logAudit(Auth::user(), 'Store Coupon', $request->all(), $validator->errors());
        return $this->validationError($validator->errors());
    }

    $appliesToAllProducts = $request->input('products_all', false);
    if ($appliesToAllProducts || (is_array($request->products) && in_array('all', $request->products))) {
        $appliesToAllProducts = true;
        $productIds = [];
    } else {
        $productIds = $request->products ?? [];
    }

    $coupon = Coupon::create([
        'name' => $request->name,
        'code' => $request->code,
        'date_from' => $request->date_from,
        'date_to' => $request->date_to,
        'min_amount' => $request->min_amount,
        'type' => $request->type,
        'applies_to_shipping' => $request->applies_to_shipping ?? false,
        'max_use_per_user' => $request->max_use_per_user,
        'max_use_per_code' => $request->max_use_per_code,
        'coupon_status_id' => $request->coupon_status_id,
        'applies_to_all_products' => $appliesToAllProducts,
        'value' => $request->value,
    ]);

    if (!empty($request->categories)) {
        $coupon->categories()->attach($request->categories);
    }

    if (!$appliesToAllProducts && !empty($productIds)) {
        $coupon->products()->attach($productIds);
    }

    $coupon->load('categories:id', 'products:id');
    $this->logAudit(Auth::user(), 'Store Coupon', $request->all(), $coupon);

    return $this->success($coupon, 'Cupon creado', 201);
}

public function update(Request $request, $id)
{
    $coupon = $this->findObject(Coupon::class, $id);

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'code' => [
            'required',
            'string',
            'max:255',
            Rule::unique('coupons', 'code')->ignore($coupon->id),
        ],
        'date_from' => 'required|date',
        'date_to' => 'required|date|after_or_equal:date_from',
        'min_amount' => 'required|numeric|min:0',
        'type' => 'required|in:Fijo,Porcentaje',
        'applies_to_shipping' => 'boolean',
        'max_use_per_user' => 'required|integer|min:0',
        'max_use_per_code' => 'required|integer|min:0',
        'coupon_status_id' => 'required|exists:coupon_statuses,id',
        'categories' => 'nullable|array',
        'categories.*' => 'integer|exists:categories,id',
        'products' => 'nullable|array',
        'products.*' => [
            'integer',
            'distinct',
            'exists:products,id',
        ],
        'products_all' => 'nullable|boolean',
        'value' => 'required|numeric|min:0',
    ]);

    if ($validator->fails()) {
        $this->logAudit(Auth::user(), 'Update Coupon', $request->all(), $validator->errors());
        return $this->validationError($validator->errors());
    }

    $appliesToAllProducts = $request->input('products_all', false);
    if ($appliesToAllProducts || (is_array($request->products) && in_array('all', $request->products))) {
        $appliesToAllProducts = true;
        $productIdsToSync = [];
    } else {
        $productIdsToSync = $request->products ?? [];
    }

    $coupon->update([
        'name' => $request->name,
        'code' => $request->code,
        'date_from' => $request->date_from,
        'date_to' => $request->date_to,
        'min_amount' => $request->min_amount,
        'type' => $request->type,
        'applies_to_shipping' => $request->applies_to_shipping ?? false,
        'max_use_per_user' => $request->max_use_per_user,
        'max_use_per_code' => $request->max_use_per_code,
        'coupon_status_id' => $request->coupon_status_id,
        'applies_to_all_products' => $appliesToAllProducts,
        'value' => $request->value,
    ]);

    $coupon->categories()->sync($request->categories ?? []);

    if ($appliesToAllProducts) {
        $coupon->products()->detach();
    } else {
        $coupon->products()->sync($productIdsToSync);
    }

    $coupon->load('categories:id', 'products:id');
    $this->logAudit(Auth::user(), 'Update Coupon', $request->all(), $coupon);

    return $this->success($coupon, 'Cupon actualizado');
}

    public function delete($id)
    {
        $coupon = $this->findObject(Coupon::class, $id);
        $coupon->delete();
        $this->logAudit(Auth::user(), 'Delete Coupon', $id, $coupon);
        return $this->success($coupon, 'Cupon eliminado');
    }

    public function validateCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $code = $request->code;
        $clientId = $request->client_id;

        $coupon = Coupon::with('categories:id,name', 'products:id,name')
            ->where('code', $code)
            ->first();

        if (!$coupon) {
            return $this->error('Código de cupón inválido', 404);
        }

        // Validaciones de vigencia
        $today = now();
        if ($today->lt($coupon->date_from) || $today->gt($coupon->date_to)) {
            return $this->error('Cupón vencido o aún no vigente', 400);
        }

        // Validación de usos por código
        if (
            $coupon->max_use_per_code > 0 &&
            $coupon->sales()->whereNotIn('sale_status_id', [5, 8, 9])->count() >= $coupon->max_use_per_code
        ) {
            return $this->error('Este cupón alcanzó el máximo de usos permitidos', 400);
        }

        // Validación de usos por usuario
        if ($clientId && $coupon->max_use_per_user > 0) {
            $usesByClient = $coupon->sales()
                ->where('client_id', $clientId)
                ->whereNotIn('sale_status_id', [5, 8, 9])
                ->count();

            if ($usesByClient >= $coupon->max_use_per_user) {
                return $this->error('Ya has usado este cupón el máximo de veces permitidas', 400);
            }
        }

        // Si pasa todas las validaciones
        $response = [
            'id' => $coupon->id,
            'name' => $coupon->name,
            'code' => $coupon->code,
            'type' => $coupon->type, // Fijo o Porcentaje
            'value' => $coupon->value, // o el campo que uses para el monto/descuento
            'applies_to_shipping' => $coupon->applies_to_shipping,
            'applies_to_all_products' => $coupon->applies_to_all_products,
            'categories' => $coupon->categories,
            'products' => $coupon->products,
        ];

        $this->logAudit(Auth::user(), 'Validate Coupon', $request->all(), $response);

        return $this->success($response, 'Cupón válido');
    }
}
