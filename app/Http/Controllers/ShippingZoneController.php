<?php

namespace App\Http\Controllers;

use App\Models\ShippingZone;
use App\Models\ShippingOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ShippingZoneController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');

        $query = ShippingZone::query()
            ->with([
                'options' => function ($q) {
                    $q->orderBy('options_order', 'asc');
                },
                'status'
            ]);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $zones = $query->get();
            $this->logAudit(Auth::user() ?? null, 'Get Shipping Zones List', $request->all(), $zones);
            return $this->success($zones, 'Zonas obtenidas');
        }

        $zones = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user() ?? null, 'Get Shipping Zones List', $request->all(), $zones);
        $metaData = [
            'current_page' => $zones->currentPage(),
            'last_page' => $zones->lastPage(),
            'per_page' => $zones->perPage(),
            'total' => $zones->total(),
            'from' => $zones->firstItem(),
            'to' => $zones->lastItem(),
        ];
        return $this->success($zones->items(), 'Zonas obtenidas', $metaData);
    }

    public function show($id)
    {
        $zone = $this->findObject(ShippingZone::class, $id);
        $zone->load([
            'options' => function ($q) {
                $q->orderBy('options_order', 'asc');
            },
            'status'
        ]);
        $this->logAudit(Auth::user() ?? null, 'Get Shipping Zone Details', $id, $zone);
        return $this->success($zone, 'Zona obtenida');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:shipping_zones,name',
            'postal_codes' => 'nullable|array',
            'status_id' => 'required|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Shipping Zone', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $zone = ShippingZone::create([
            'name' => $request->name,
            'postal_codes' => $request->postal_codes ?? null,
            'status_id' => $request->status_id,
        ]);

        // Crear automáticamente el método "Envío Gratuito"
        ShippingOption::create([
            'zone_id' => $zone->id,
            'name' => 'Envío Gratuito',
            'price' => 0,
            'status_id' => $zone->status_id,
            'is_shipping_free' => 1
        ]);

        $zone->load('options', 'status');
        $this->logAudit(Auth::user(), 'Store Shipping Zone', $request->all(), $zone);
        return $this->success($zone, 'Zona creada con método Envío Gratuito asignado');
    }

    public function update(Request $request, $id)
    {
        $zone = $this->findObject(ShippingZone::class, $id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:shipping_zones,name,' . $zone->id,
            'postal_codes' => 'required|array',
            'status_id' => 'required|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Shipping Zone', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $zone->update([
            'name' => $request->name,
            'postal_codes' => $request->postal_codes,
            'status_id' => $request->status_id,
        ]);

        $zone->load('options', 'status');
        $this->logAudit(Auth::user(), 'Update Shipping Zone', $request->all(), $zone);
        return $this->success($zone, 'Zona actualizada');
    }

    public function destroy($id)
    {
        $zone = $this->findObject(ShippingZone::class, $id);
        $zone->delete();
        $this->logAudit(Auth::user(), 'Delete Shipping Zone', $id, $zone);
        return $this->success($zone, 'Zona eliminada');
    }

    public function toggleStatus($id)
    {
        $zone = ShippingZone::findOrFail($id);

        $zone->status_id = $zone->status_id == 1 ? 2 : 1;
        // Ej: 1 = Activo, 2 = Inactivo
        $zone->save();
        $zone->load('status');

        return response()->json([
            'message' => 'Estado de la opción actualizado',
            'data' => $zone
        ]);
    }
}
