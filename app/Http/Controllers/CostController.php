<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class CostController extends Controller
{
    use FindObject, ApiResponse, Auditable;
    
    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $query = Cost::query()->with('status_id');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('statusId', $statusId);
        }

        $costs = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $costs->currentPage(),
            'last_page' => $costs->lastPage(),
            'per_page' => $costs->perPage(),
            'total' => $costs->total(),
            'from' => $costs->firstItem(),
            'to' => $costs->lastItem(),
        ];
        $this->logAudit(Auth::user(), 'Get Costs List', $request->all(), $costs);
        return $this->success([
            'data' => $costs->items(),
            'meta_data' => $metaData,
        ], 'Costos obtenidos');
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
        return $this->success($cost, 'Costo creado', 201);
    }

    public function show($id)
    {
        $cost = $this->findObject(Cost::class, $id);
        $cost->load('status', 'prices');
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

        $cost->update([
            'name' => $request->name,
            'price' => $request->price,
            'status_id' => $request->statusId ?? 1,
        ]);
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
