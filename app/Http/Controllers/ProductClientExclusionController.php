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
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Product Client Exclusion', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $client = Client::find($request->client_id);

        if ($client->client_type_id !== 2) {
            return $this->error('El cliente no es mayorista', 422);
        }

        if ($product->excludedClients()->where('client_id', $client->id)->exists()) {
            return $this->error('El cliente ya está excluido de este producto', 422);
        }

        $product->excludedClients()->attach($client->id);

        $this->logAudit(Auth::user(), 'Store Product Client Exclusion', $request->all(), ['product_id' => $product->id, 'client_id' => $client->id]);

        return $this->success(null, 'Cliente excluido del producto correctamente');
    }

    public function destroy($productId, $clientId)
    {
        $product = $this->findObject(Product::class, $productId);

        if (!$product) {
            return $this->error('Producto no encontrado', 404);
        }

        if (!$product->excludedClients()->where('client_id', $clientId)->exists()) {
            return $this->error('El cliente no está excluido de este producto', 404);
        }

        $product->excludedClients()->detach($clientId);

        $this->logAudit(Auth::user(), 'Destroy Product Client Exclusion', ['product_id' => $productId, 'client_id' => $clientId], null);

        return $this->success(null, 'Exclusión eliminada correctamente');
    }
}
