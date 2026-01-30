<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\ClientAddress;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;

class ClientAddressController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $client = Auth::guard('client')->user();

        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');

        $query = ClientAddress::query()
            ->where('client_id', $client->id)
            ->with('locality');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $addresses = $query->get();
            return $this->success($addresses, 'Direcciones obtenidas');
        }

        $addresses = $query->paginate($perPage, ['*'], 'page', $page);
        
        $metaData = [
            'current_page' => $addresses->currentPage(),
            'last_page' => $addresses->lastPage(),
            'per_page' => $addresses->perPage(),
            'total' => $addresses->total(),
            'from' => $addresses->firstItem(),
            'to' => $addresses->lastItem(),
        ];

        return $this->success($addresses->items(), 'Direcciones obtenidas', $metaData);
    }

    public function store(Request $request)
    {
        $client = Auth::guard('client')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'localityId' => 'required|exists:localities,id',
            'postalCode' => 'required|string',
            'observations' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $this->logAudit(null, 'Store Client Address', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $client = $this->findObject(Client::class, $client->id);

        $address = $client->addresses()->create([
            'name' => $request->name,
            'address' => $request->address,
            'locality_id' => $request->localityId,
            'postal_code' => $request->postalCode,
            'observations' => $request->observations ?? null,
        ]);

        $client->load('addresses');
        $this->logAudit(null, 'Store Client Address', $request->all(), $address);

        return $this->success($address, 'Dirección creada');
    }

    public function update(Request $request, $addressId)
    {
        $client = Auth::guard('client')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'localityId' => 'required|exists:localities,id',
            'postalCode' => 'required|string',
            'observations' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $this->logAudit(null, 'Update Client Address', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Verificamos que la dirección pertenezca al cliente autenticado
        $address = ClientAddress::where('id', $addressId)
            ->where('client_id', $client->id)
            ->first();

        if (!$address) {
            return $this->error('La dirección no pertenece al cliente autenticado', 403);
        }

        $address->update([
            'name' => $request->name,
            'address' => $request->address,
            'locality_id' => $request->localityId,
            'postal_code' => $request->postalCode,
            'observations' => $request->observations ?? null,
        ]);

        $this->logAudit(null, 'Update Client Address', $request->all(), $address);

        return $this->success($address, 'Dirección actualizada');
    }

}
