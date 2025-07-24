<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientShipping;
use App\Models\ClientWholesale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Illuminate\Validation\Rule;
class ClientController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = Client::query()->select('id', 'client_type_id', 'name', 'lastName', 'email', 'phone', 'status_id', 'cuit')->with('wholesales');         
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
            return $this->success($clients, 'Clientes obtenidos');
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
        return $this->success($clients->items(), 'Clientes obtenidos', $metaData);
    }

    public function show($id)
    {
        $client = $this->findObject(Client::class, $id);
        $client->load('clientType', 'generalStatus', 'shippings.locality', 'wholesale.locality'); 
        $this->logAudit(Auth::user(), 'Get Client Details', $id, $client);
        return $this->success($client, 'Cliente obtenido');
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
            'cuit' => 'nullable|string|max:20|unique:clients,cuit',
            'billingData' => 'nullable|json',
            'statusId' => 'nullable|exists:general_statuses,id',
            'wholesale_data' => 'nullable|array',
            'wholesale_data.*.name' => 'required|string|max:255',
            'wholesale_data.*.localityId' => 'required|exists:localities,id',
            'wholesale_data.*.address' => 'required|string|max:255',
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
            'cuit' => $request->cuit, 
            'billing_data' => $request->billingData,
            'status_id' => $request->statusId,
        ]);

        if ($request->has('wholesale_data') && is_array($request->wholesale_data)) {
            foreach ($request->wholesale_data as $wholesaleItem) {
                $client->wholesales()->create([
                    'name' => $wholesaleItem['name'],
                    'locality_id' => $wholesaleItem['localityId'],
                    'address' => $wholesaleItem['address'],
                ]);
            }
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
        $client->load('wholesales');
        $validator = Validator::make($request->all(), [
            'clientTypeId' => 'required|exists:client_types,id',
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => Rule::unique('clients', 'email')->ignore($client->id), 
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'cuit' => Rule::unique('clients', 'cuit')->ignore($client->id), 
            'billingData' => 'nullable|json',
            'statusId' => 'nullable|exists:general_statuses,id',

            'wholesale_data' => 'nullable|array',
            'wholesale_data.*.id' => 'nullable|integer|exists:client_wholesales,id', 
            'wholesale_data.*.name' => 'required|string|max:255',
            'wholesale_data.*.localityId' => 'required|exists:localities,id',
            'wholesale_data.*.address' => 'required|string|max:255',
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
            'cuit' => $request->cuit, 
            'billing_data' => $request->billingData,
            'status_id' => $request->statusId,
        ]);

        if ($request->password) {
            $client->password = bcrypt($request->password);
            $client->save(); 
        }

        $existingWholesaleIds = $client->wholesales->pluck('id')->toArray();
        $incomingWholesaleIds = collect($request->wholesale_data ?? [])->pluck('id')->toArray();     
        $wholesalesToDelete = array_diff($existingWholesaleIds, $incomingWholesaleIds);
        if (!empty($wholesalesToDelete)) {
            ClientWholesale::whereIn('id', $wholesalesToDelete)->delete();
        }

        if ($request->has('wholesale_data') && is_array($request->wholesale_data)) {
            foreach ($request->wholesale_data as $wholesaleItem) {
                if (isset($wholesaleItem['id']) && in_array($wholesaleItem['id'], $existingWholesaleIds)) {
                    ClientWholesale::where('id', $wholesaleItem['id'])->update([
                        'name' => $wholesaleItem['name'],
                        'locality_id' => $wholesaleItem['localityId'],
                        'address' => $wholesaleItem['address'],
                    ]);
                } else {
                    $client->wholesales()->create([
                        'name' => $wholesaleItem['name'],
                        'locality_id' => $wholesaleItem['localityId'],
                        'address' => $wholesaleItem['address'],
                    ]);
                }
            }
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