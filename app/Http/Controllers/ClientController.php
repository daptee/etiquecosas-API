<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientWholesale;
use APp\Models\ClientAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
class ClientController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $clientTypeId = $request->query('clientTypeId'); // id del tipo de cliente
        $statusId = $request->query('status_id'); // 👈 nuevo parámetro para filtrar por estado

        $query = Client::query()
            ->select('id', 'client_type_id', 'name', 'lastName', 'email', 'phone', 'status_id', 'cuit', 'business_name')
            ->with(['wholesales', 'addresses', 'clientType', 'generalStatus']);

        // 🔍 Buscador: por nombre, apellido, email o cuit
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cuit', 'like', "%{$search}%"); // para mayoristas
            });
        }

        // 🏷️ Filtro por tipo de cliente (común o mayorista)
        if ($clientTypeId) {
            $query->where('client_type_id', $clientTypeId);
        }

        // ✅ Filtro por estado
        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        $query->orderBy('name', 'asc');

        // 📌 Sin paginación → traer todo
        if (!$perPage) {
            $clients = $query->get();
            return $this->success($clients, 'Clientes obtenidos');
        }

        // 📌 Con paginación
        $clients = $query->paginate($perPage, ['*'], 'page', $page);

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
        $client->load('clientType', 'generalStatus', 'wholesales.locality', 'addresses.locality');
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
            'statusId' => 'nullable|exists:general_statuses,id',
            'businessName' => 'nullable|string|max:255',
            'wholesale_data' => 'nullable|array',
            'wholesale_data.*.name' => 'required|string|max:255',
            'wholesale_data.*.localityId' => 'required|exists:localities,id',
            'wholesale_data.*.address' => 'required|string|max:255',
            'wholesale_data.*.postalCode' => 'required|string',
            'address_data' => 'nullable|array',
            'address_data.*.name' => 'required|string',
            'address_data.*.address' => 'required|string',
            'address_data.*.localityId' => 'required|exists:localities,id',
            'address_data.*.postalCode' => 'required|string',
            'address_data.*.observations' => 'nullable|string',
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
            'status_id' => $request->statusId,
            'business_name' => $request->businessName ?? null,
        ]);

        if ($request->has('wholesale_data') && is_array($request->wholesale_data)) {
            foreach ($request->wholesale_data as $wholesaleItem) {
                $client->wholesales()->create([
                    'name' => $wholesaleItem['name'],
                    'locality_id' => $wholesaleItem['localityId'],
                    'address' => $wholesaleItem['address'],
                    'postal_code' => $wholesaleItem['postalCode'],
                ]);
            }
        }

        if ($request->has('address_data')) {
            foreach ($request->address_data as $address) {
                $client->addresses()->create([
                    'client_id' => $client->id,
                    'name' => $address['name'],
                    'address' => $address['address'],
                    'locality_id' => $address['localityId'],
                    'postal_code' => $address['postalCode'],
                    'observations' => $address['observations'] ?? null,
                ]);
            }
        }

        $client->load([
            'clientType',
            'wholesales',
            'addresses'
        ]);
        $this->logAudit(Auth::user(), 'Store Client', $request->all(), $client);
        return $this->success($client, 'Cliente creado');
    }

    public function update(Request $request, $id)
    {
        $client = $this->findObject(Client::class, $id);
        $client->load('wholesales');
        $validator = Validator::make($request->all(), [
            'clientTypeId' => 'nullable|exists:client_types,id',
            'name' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => Rule::unique('clients', 'email')->ignore($client->id),
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'cuit' => Rule::unique('clients', 'cuit')->ignore($client->id),
            'statusId' => 'nullable|exists:general_statuses,id',
            'businessName' => 'nullable|string|max:255',
            'wholesale_data' => 'nullable|array',
            'wholesale_data.*.id' => 'nullable|integer|exists:client_wholesales,id',
            'wholesale_data.*.name' => 'required|string|max:255',
            'wholesale_data.*.localityId' => 'required|exists:localities,id',
            'wholesale_data.*.address' => 'required|string|max:255',
            'wholesale_data.*.postalCode' => 'required|string',
            'address_data' => 'nullable|array',
            'address_data.*.id' => 'nullable|exists:client_addresses,id',
            'address_data.*.name' => 'required|string',
            'address_data.*.address' => 'required|string',
            'address_data.*.localityId' => 'required|exists:localities,id',
            'address_data.*.postalCode' => 'required|string',
            'address_data.*.observations' => 'nullable|string',
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
            'status_id' => $request->statusId,
            'business_name' => $request->businessName ?? null,
        ]);

        if ($request->password) {
            $client->password = bcrypt($request->password);
            $client->save();
        }

        /* $existingAddressIds = $client->addresses->pluck('id')->toArray();
        $incomingAddressIds = collect($request->billing_data ?? [])->pluck('id')->toArray();
        $addressesToDelete = array_diff($existingAddressIds, $incomingAddressIds);
        if (!empty($addressesToDelete)) {
            ClientAddress::whereIn('id', $addressesToDelete)->delete();
        }

        if ($request->has('billing_data') && is_array($request->billing_data)) {
            foreach ($request->billing_data as $billingItem) {
                if (isset($billingItem['id']) && in_array($billingItem['id'], $existingAddressIds)) {
                    ClientAddress::where('id', $billingItem['id'])->update([
                        'locality_id' => $billingItem['localityId'],
                        'address' => $billingItem['address'],
                    ]);
                } else {
                    $client->addresses()->create([
                        'locality_id' => $billingItem['localityId'],
                        'address' => $billingItem['address'],
                    ]);
                }
            }
        } */

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
                        'postal_code' => $wholesaleItem['postalCode'],
                    ]);
                } else {
                    $client->wholesales()->create([
                        'name' => $wholesaleItem['name'],
                        'locality_id' => $wholesaleItem['localityId'],
                        'address' => $wholesaleItem['address'],
                        'postal_code' => $wholesaleItem['postalCode'],
                    ]);
                }
            }
        }

        $existingAddressIds = $client->addresses()->pluck('id')->toArray();
        $incomingAddressIds = collect($request->address_data ?? [])->pluck('id')->filter()->toArray();
        $toDelete = array_diff($existingAddressIds, $incomingAddressIds);
        ClientAddress::whereIn('id', $toDelete)->delete();
        if ($request->has('address_data')) {
            foreach ($request->address_data as $address) {
                if (isset($address['id']) && in_array($address['id'], $existingAddressIds)) {
                    ClientAddress::where('id', $address['id'])->update([
                        'name' => $address['name'],
                        'address' => $address['address'],
                        'locality_id' => $address['localityId'],
                        'postal_code' => $address['postalCode'],
                        'observations' => $address['observations'] ?? null,
                    ]);
                } else {
                    ClientAddress::create([
                        'client_id' => $client->id,
                        'name' => $address['name'],
                        'address' => $address['address'],
                        'locality_id' => $address['localityId'],
                        'postal_code' => $address['postalCode'],
                        'observations' => $address['observations'] ?? null,
                    ]);
                }
            }
        }

        $client->load([
            'clientType',
            'wholesales',
            'addresses'
        ]);
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

    public function updateProfile(Request $request)
    {

        $client = Auth::guard('client')->user();
        if (!$client) {
            return $this->error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:clients,email,' . $client->id,
            'current_password' => 'nullable|string|min:8',
            'password' => 'nullable|string|min:8|confirmed|different:current_password',
        ]);

        if ($validator->fails()) {
            $this->logAudit(null, 'Update Profile Client Failed', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        if ($request->has('current_password') && $request->has('password')) {
            if (!Hash::check($request->current_password, $client->password)) {
                $this->logAudit(null, 'Update Password client', $request->all(), ['error' => 'Contraseña actual incorrecta']);
                return $this->validationError(['current_password' => ['La contraseña actual es incorrecta.']]);
            }
            ;

            $client->password = Hash::make($request->password);
            $client->save();
        }

        $client->name = $request->input('name', $client->name);
        $client->lastname = $request->input('lastName', $client->lastname);
        $client->email = $request->input('email', $client->email);
        $client->save();
        $this->logAudit(null, 'Update Profile', $request->all(), $client);
        $token = JWTAuth::fromUser($client);
        return $this->success([
            'user' => $client,
            'token' => $token
        ], 'Perfil actualizado y nuevo token generado');
    }
}