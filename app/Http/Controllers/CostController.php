<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Services\ProductPriceService; 

class CostController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $query = Cost::query()->with('generalStatus');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('statusId', $statusId);
        }

        $query->orderBy('name', 'asc');
        if (!$perPage) {
            $costs = $query->get();
            $this->logAudit(Auth::user(), 'Get Costs List', $request->all(), $costs);
            return $this->success($costs, 'Costos obtenidos');
        }

        $costs = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Costs List', $request->all(), $costs);
        $metaData = [
            'current_page' => $costs->currentPage(),
            'last_page' => $costs->lastPage(),
            'per_page' => $costs->perPage(),
            'total' => $costs->total(),
            'from' => $costs->firstItem(),
            'to' => $costs->lastItem(),
        ];        
        return $this->success($costs->items(), 'Costos obtenidos', $metaData);
    }       

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:costs',
            'price' => 'required|numeric|min:0',
            'statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Cost', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $cost = Cost::create([
            'name' => $request->name,
            'price' => $request->price,
            'status_id' => $request->statusId ?? 1,
        ]);
        $this->logAudit(Auth::user(), 'Store Cost', $request->all(), $cost);
        return $this->success($cost, 'Costo creado');
    }

    public function show($id)
    {
        $cost = $this->findObject(Cost::class, $id);
        $cost->load('generalStatus', 'prices');
        $this->logAudit(Auth::user(), 'Get Cost Details', $id, $cost);
        return $this->success($cost, 'Detalles del costo obtenidos');
    }

    public function update(Request $request, $id)
    {
        $cost = $this->findObject(Cost::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:costs,name,' . $cost->id,
            'price' => 'required|numeric|min:0',
            'statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Cost', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $oldPrice = $cost->price;

        $cost->update([
            'name' => $request->name,
            'price' => $request->price,
            'status_id' => $request->statusId ?? 1,
        ]);

        // Si el precio cambió, actualizar precios de productos que usan este costo
        if ($oldPrice != $request->price) {
            $priceService = new ProductPriceService();
            $updatedProducts = $priceService->updateProductsUsingCost($cost->id);

            $this->logAudit(Auth::user(), 'Update Cost', $request->all(), [
                'cost' => $cost,
                'products_updated' => $updatedProducts,
            ]);

            return $this->success([
                'cost' => $cost,
                'products_updated' => $updatedProducts,
            ], "Costo actualizado. Se actualizaron los precios de {$updatedProducts} productos.");
        }

        $this->logAudit(Auth::user(), 'Update Cost', $request->all(), $cost);
        return $this->success($cost, 'Costo actualizado');
    }

    public function delete($id)
    {
        $cost = $this->findObject(Cost::class, $id);
        $cost->delete();
        $this->logAudit(Auth::user(), 'Delete Cost', $id, $cost);
        return $this->success($cost, 'Costo eliminado');
    }
}
