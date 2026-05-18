<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductClientExclusionController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request, $productId)
    {
        $product = $this->findObject(Product::class, $productId);

        if (!$product) {
            return $this->error('Producto no encontrado', 404);
        }

        $excludedClients = $product->excludedClients()
            ->select('clients.id', 'clients.name', 'clients.lastname', 'clients.email', 'clients.business_name', 'clients.cuit')
            ->get();

        return $this->success($excludedClients, 'Clientes excluidos obtenidos');
    }

    public function store(Request $request, $productId)
    {
        $product = $this->findObject(Product::class, $productId);

        if (!$product) {
            return $this->error('Producto no encontrado', 404);
        }

        if (!$product->is_wholesale) {
            return $this->error('El producto no es mayorista', 422);
        }

        $validator = Validator::make($request->all(), [
            'client_ids'   => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:clients,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Product Client Exclusion', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $clients = Client::whereIn('id', $request->client_ids)->get();

        $nonWholesale = $clients->where('client_type_id', '!=', 2)->pluck('id');
        if ($nonWholesale->isNotEmpty()) {
            return $this->error('Los siguientes clientes no son mayoristas: ' . $nonWholesale->join(', '), 422);
        }

        $alreadyExcluded = $product->excludedClients()->whereIn('clients.id', $request->client_ids)->pluck('clients.id');
        $toAttach = collect($request->client_ids)->diff($alreadyExcluded)->values()->toArray();

        if (empty($toAttach)) {
            return $this->error('Todos los clientes indicados ya están excluidos de este producto', 422);
        }

        $product->excludedClients()->attach($toAttach);

        $this->logAudit(Auth::user(), 'Store Product Client Exclusion', $request->all(), ['product_id' => $product->id, 'client_ids' => $toAttach]);

        return $this->success(null, 'Clientes excluidos del producto correctamente');
    }

    public function destroy(Request $request, $productId)
    {
        $product = $this->findObject(Product::class, $productId);

        if (!$product) {
            return $this->error('Producto no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'client_ids'   => 'required|array|min:1',
            'client_ids.*' => 'integer|exists:clients,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Destroy Product Client Exclusion', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $existingExclusions = $product->excludedClients()->whereIn('clients.id', $request->client_ids)->pluck('clients.id');

        if ($existingExclusions->isEmpty()) {
            return $this->error('Ninguno de los clientes indicados está excluido de este producto', 404);
        }

        $product->excludedClients()->detach($existingExclusions->toArray());

        $this->logAudit(Auth::user(), 'Destroy Product Client Exclusion', ['product_id' => $productId, 'client_ids' => $existingExclusions->toArray()], null);

        return $this->success(null, 'Exclusiones eliminadas correctamente');
    }
}
