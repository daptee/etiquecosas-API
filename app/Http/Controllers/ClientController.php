<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientShipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ClientController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = Client::query()->select('id', 'client_type_id', 'name', 'lastname', 'email', 'phone');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('lastname', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $clients = $query->get();
            $this->logAudit(Auth::user(), 'Get Clients List', $request->all(), $clients);
            return $this->success([
                'data' => $clients,
                'meta_data' => null,
            ], 'Clientes obtenidos');
        }

        $clients = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $clients->currentPage(),
            'last_page' => $clients->lastPage(),
            'per_page' => $clients->perPage(),
            'total' => $clients->total(),
            'from' => $clients->firstItem(),
            'to' => $clients->lastItem(),
        ];
        $this->logAudit(Auth::user(), 'Get Clients List', $request->all(), $clients);
        return $this->success([
            'data' => $clients->items(),
            'meta_data' => $metaData,
        ], 'Clientes obtenidos');
    }

    public function show($id)
    {
        $client = $this->findObject(Client::class, $id);
        $client->load('type', 'status', 'shippings.locality');
        $this->logAudit(Auth::user(), 'Get Client Details', $id, $client);
        return $this->success($client, 'Detalles del cliente obtenidos');
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clientTypeId' => 'required|exists:client_types,id',
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'billingData' => 'nullable|json',
            'wholesaleData' => 'nullable|json',
            'statusId' => 'nullable|exists:statuses,id',
            'shippings' => 'nullable|array',
            'shippings.*.name' => 'required|string',
            'shippings.*.address' => 'required|string',
            'shippings.*.locality_id' => 'required|exists:localities,id',
            'shippings.*.postalCode' => 'required|string',
            'shippings.*.observations' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Client', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $client = Client::create([
            'client_type_id' => $request->clientTypeId,
            'name' => $request->name,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'password' => $request->password ? bcrypt($request->password) : null,
            'phone' => $request->phone,
            'billing_data' => $request->billingData,
            'wholesale_data' => $request->wholesaleData,
            'status_id' => $request->statusId,
        ]);
        if ($request->has('shippings')) {
            foreach ($request->shippings as $shipping) {
                ClientShipping::create([
                    'client_id' => $client->id,
                    'name' => $shipping['name'],
                    'address' => $shipping['address'],
                    'locality_id' => $shipping['localityId'],
                    'postal_code' => $shipping['postalCode'],
                    'observations' => $shipping['observations'] ?? null,
                ]);
            }
        }

        $this->logAudit(Auth::user(), 'Store Client', $request->all(), $client);
        return $this->success($client, 'Cliente creado', 201);
    }

    public function update(Request $request, $id)
    {
        $client = $this->findObject(Client::class, $id);
        $validator = Validator::make($request->all(), [
            'clientTypeId' => 'required|exists:client_types,id',
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email,' . $client->id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'billingData' => 'nullable|json',
            'wholesaleData' => 'nullable|json',
            'statusId' => 'nullable|exists:statuses,id',
            'shippings' => 'nullable|array',
            'shippings.*.id' => 'nullable|exists:clients_shipping,id',
            'shippings.*.name' => 'required|string',
            'shippings.*.address' => 'required|string',
            'shippings.*.localityId' => 'required|exists:localities,id',
            'shippings.*.postalCode' => 'required|string',
            'shippings.*.observations' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Client', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $client->update([
            'client_type_id' => $request->clientTypeId,
            'name' => $request->name,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'phone' => $request->phone,
            'billing_data' => $request->billingData,
            'wholesale_data' => $request->wholesaleData,
            'status_id' => $request->statusId,
        ]);
        if ($request->password) {
            $client->password = bcrypt($request->password);
            $client->save();
        }

        $existingShippingIds = $client->shippings()->pluck('id')->toArray();
        $incomingShippingIds = collect($request->shippings ?? [])->pluck('id')->filter()->toArray();
        $toDelete = array_diff($existingShippingIds, $incomingShippingIds);
        ClientShipping::whereIn('id', $toDelete)->delete();
        if ($request->has('shippings')) {
            foreach ($request->shippings as $shipping) {
                if (isset($shipping['id']) && in_array($shipping['id'], $existingShippingIds)) {
                    ClientShipping::where('id', $shipping['id'])->update([
                        'name' => $shipping['name'],
                        'address' => $shipping['address'],
                        'locality_id' => $shipping['localityId'],
                        'postal_code' => $shipping['postalCode'],
                        'observations' => $shipping['observations'] ?? null,
                    ]);
                } else {
                    ClientShipping::create([
                        'client_id' => $client->id,
                        'name' => $shipping['name'],
                        'address' => $shipping['address'],
                        'locality_id' => $shipping['localityId'],
                        'postal_code' => $shipping['postalCode'],
                        'observations' => $shipping['observations'] ?? null,
                    ]);
                }
            }
        }

        $this->logAudit(Auth::user(), 'Update Client', $request->all(), $client);
        return $this->success($client, 'Cliente actualizado');
    }

    public function delete($id)
    {
        $client = $this->findObject(Client::class, $id);
        $client->delete();
        $this->logAudit(Auth::user(), 'Delete Client', $id, $client);
        return $this->success($client, 'Cliente eliminado');
    }
}
