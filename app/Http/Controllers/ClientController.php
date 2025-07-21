<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientShipping;
use App\Models\ClientWholesale; // Importa el nuevo modelo
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Illuminate\Validation\Rule; // Importar para validación unique

class ClientController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = Client::query()->select('id', 'client_type_id', 'name', 'lastName', 'email', 'phone', 'status_id')->with('wholesale');         
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('lastName', 'like', "%{$search}%")
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
        $this->logAudit(Auth::user(), 'Get Clients List', $request->all(), $clients);
        $metaData = [
            'current_page' => $clients->currentPage(),
            'last_page' => $clients->lastPage(),
            'per_page' => $clients->perPage(),
            'total' => $clients->total(),
            'from' => $clients->firstItem(),
            'to' => $clients->lastItem(),
        ];        
        return $this->success([
            'data' => $clients->items(),
            'meta_data' => $metaData,
        ], 'Clientes obtenidos');
    }

    public function show($id)
    {
        $client = $this->findObject(Client::class, $id);
        $client->load('clientType', 'generalStatus', 'shippings.locality', 'wholesale.locality'); 
        $this->logAudit(Auth::user(), 'Get Client Details', $id, $client);
        return $this->success($client, 'Cliente obtenidos');
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
            'statusId' => 'nullable|exists:general_statuses,id',
            'wholesale.cuit' => 'nullable|string|max:20|unique:client_wholesales,cuit',
            'wholesale.name' => 'nullable|string|max:255',
            'wholesale.localityId' => 'nullable|exists:localities,id',
            'wholesale.address' => 'nullable|string|max:255',
            'shippings' => 'nullable|array',
            'shippings.*.name' => 'required|string',
            'shippings.*.address' => 'required|string',
            'shippings.*.localityId' => 'required|exists:localities,id',
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
            'status_id' => $request->statusId,
        ]);

        if ($request->has('wholesale') && !empty($request->wholesale['cuit'])) {
            $client->wholesale()->create([
                'cuit' => $request->wholesale['cuit'],
                'name' => $request->wholesale['name'] ?? null,
                'locality_id' => $request->wholesale['localityId'] ?? null,
                'address' => $request->wholesale['address'] ?? null,
            ]);
        }

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
        return $this->success($client, 'Cliente creado');
    }

    public function update(Request $request, $id)
    {
        $client = $this->findObject(Client::class, $id);
        $validator = Validator::make($request->all(), [
            'clientTypeId' => 'required|exists:client_types,id',
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => Rule::unique('clients', 'email')->ignore($client->id), 
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'billingData' => 'nullable|json',
            'statusId' => 'nullable|exists:general_statuses,id',
            'wholesale.cuit' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('client_wholesales', 'cuit')->ignore($client->wholesale->id ?? null, 'id'), 
            ],
            'wholesale.name' => 'nullable|string|max:255',
            'wholesale.localityId' => 'nullable|exists:localities,id',
            'wholesale.address' => 'nullable|string|max:255',
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
            'status_id' => $request->statusId,
        ]);

        if ($request->password) {
            $client->password = bcrypt($request->password);
            $client->save();
        }

        if ($request->has('wholesale') && !empty($request->wholesale['cuit'])) {
            if ($client->wholesale) {
                $client->wholesale->update([
                    'cuit' => $request->wholesale['cuit'],
                    'name' => $request->wholesale['name'] ?? $client->wholesale->name,
                    'locality_id' => $request->wholesale['localityId'] ?? $client->wholesale->locality_id,
                    'address' => $request->wholesale['address'] ?? $client->wholesale->address,
                ]);
            } else {
                $client->wholesale()->create([
                    'cuit' => $request->wholesale['cuit'],
                    'name' => $request->wholesale['name'] ?? null,
                    'locality_id' => $request->wholesale['localityId'] ?? null,
                    'address' => $request->wholesale['address'] ?? null,
                ]);
            }
        } elseif ($client->wholesale && $request->input('wholesale') === null) {
            $client->wholesale->delete();
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