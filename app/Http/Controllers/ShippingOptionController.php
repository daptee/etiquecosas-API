<?php

namespace App\Http\Controllers;

use App\Models\ShippingOption;
use App\Models\ShippingZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Illuminate\Validation\Rule;

class ShippingOptionController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $zoneId = $request->query('zone_id'); // nuevo filtro por zona

        $query = ShippingOption::with(['zone', 'status']);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $options = $query->get();
            $this->logAudit(Auth::user(), 'Get Shipping Options List', $request->all(), $options);
            return $this->success($options, 'Opciones de envío obtenidas');
        }

        $options = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Shipping Options List', $request->all(), $options);
        $metaData = [
            'current_page' => $options->currentPage(),
            'last_page' => $options->lastPage(),
            'per_page' => $options->perPage(),
            'total' => $options->total(),
            'from' => $options->firstItem(),
            'to' => $options->lastItem(),
        ];
        return $this->success($options->items(), 'Opciones de envío obtenidas', $metaData);
    }

    public function show($id)
    {
        $option = $this->findObject(ShippingOption::class, $id);
        $option->load(['zone', 'status']);
        $this->logAudit(Auth::user(), 'Get Shipping Option Details', $id, $option);
        return $this->success($option, 'Opción de envío obtenida');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|exists:shipping_zones,id',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shipping_options')->where(function ($query) use ($request) {
                    return $query->where('zone_id', $request->zone_id);
                }),
            ],
            'price' => 'required|numeric|min:0',
            'status_id' => 'required|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Shipping Option', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $option = ShippingOption::create([
            'zone_id' => $request->zone_id,
            'name' => $request->name,
            'price' => $request->price,
            'status_id' => $request->status_id,
        ]);

        $option->load(['zone', 'status']);
        $this->logAudit(Auth::user(), 'Store Shipping Option', $request->all(), $option);
        return $this->success($option, 'Opción de envío creada');
    }

    public function update(Request $request, $id)
    {
        $option = $this->findObject(ShippingOption::class, $id);

        $validator = Validator::make($request->all(), [
            'zone_id' => 'sometimes|exists:shipping_zones,id',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shipping_options')
                    ->where(function ($query) use ($request, $option) {
                        $zoneId = $request->zone_id ?? $option->zone_id;
                        return $query->where('zone_id', $zoneId);
                    })
                    ->ignore($option->id),
            ],
            'price' => 'sometimes|numeric|min:0',
            'status_id' => 'sometimes|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Shipping Option', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $option->update($request->only(['zone_id', 'name', 'price', 'status_id']));
        $option->load(['zone', 'status']);
        $this->logAudit(Auth::user(), 'Update Shipping Option', $request->all(), $option);
        return $this->success($option, 'Opción de envío actualizada');
    }

    public function destroy($id)
    {
        $option = $this->findObject(ShippingOption::class, $id);
        $option->delete();
        $this->logAudit(Auth::user(), 'Delete Shipping Option', $id, $option);
        return $this->success($option, 'Opción de envío eliminada');
    }

    public function toggleStatus($id)
    {
        $option = $this->findObject(ShippingOption::class, $id);
        $option->status_id = $option->status_id == 1 ? 2 : 1; // 1=Activo, 2=Inactivo
        $option->save();
        $option->load(['zone', 'status']);
        $this->logAudit(Auth::user(), 'Toggle Shipping Option Status', $id, $option);
        return $this->success($option, 'Estado de la opción actualizado');
    }
}
