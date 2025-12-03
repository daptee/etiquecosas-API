<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\ClientWholesale;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;

class ClientWholesaleController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $client = Auth::guard('client')->user();
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');

        $query = ClientWholesale::query()
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
            $wholesales = $query->get();
            $this->logAudit(null, 'Get Client Wholesales', $request->all(), $wholesales->first());
            return $this->success($wholesales, 'Clientes mayoristas obtenidos');
        }

        $wholesales = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(null, 'Get Client Wholesales', $request->all(), $wholesales->first());

        $metaData = [
            'current_page' => $wholesales->currentPage(),
            'last_page' => $wholesales->lastPage(),
            'per_page' => $wholesales->perPage(),
            'total' => $wholesales->total(),
            'from' => $wholesales->firstItem(),
            'to' => $wholesales->lastItem(),
        ];

        return $this->success($wholesales->items(), 'Clientes mayoristas obtenidos', $metaData);
    }

    public function store(Request $request)
    {
        $client = Auth::guard('client')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'localityId' => 'required|exists:localities,id',
            'address' => 'required|string|max:255',
            'businessName' => 'required|string|max:255',
            'postalCode' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->logAudit(null, 'Store Client Wholesale', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $client = $this->findObject(Client::class, $client->id);

        $wholesale = $client->wholesales()->create([
            'name' => $request->name,
            'locality_id' => $request->localityId,
            'address' => $request->address,
            'business_name' => $request->businessName,
            'postal_code' => $request->postalCode,
        ]);

        $client->load('wholesales');
        $this->logAudit(null, 'Store Client Wholesale', $request->all(), $wholesale);

        return $this->success($wholesale, 'Cliente mayorista creado');
    }


    public function update(Request $request, $wholesaleId)
    {
        $client = Auth::guard('client')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'localityId' => 'required|exists:localities,id',
            'address' => 'required|string|max:255',
            'businessName' => 'required|string|max:255',
            'postalCode' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->logAudit(null, 'Update Client Wholesale', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Validamos que el wholesale pertenezca al cliente autenticado
        $wholesale = ClientWholesale::where('id', $wholesaleId)
            ->where('client_id', $client->id)
            ->first();

        if (!$wholesale) {
            return $this->error('El cliente mayorista no pertenece al cliente autenticado', 403);
        }

        $wholesale->update([
            'name' => $request->name,
            'locality_id' => $request->localityId,
            'address' => $request->address,
            'business_name' => $request->businessName,
            'postal_code' => $request->postalCode,
        ]);

        $this->logAudit(null, 'Update Client Wholesale', $request->all(), $wholesale);

        return $this->success($wholesale, 'Cliente mayorista actualizado');
    }

}
