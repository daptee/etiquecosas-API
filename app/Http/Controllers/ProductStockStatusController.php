<?php

namespace App\Http\Controllers;

use App\Models\ProductStockStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ProductStockStatusController extends Controller
{
    use FindObject, ApiResponse, Auditable;
     
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = ProductStockStatus::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $productStockStatuses = $query->get();
            $this->logAudit(Auth::user(), 'Get Product Stock Status List', $request->all(), $productStockStatuses);
            return $this->success($productStockStatuses, 'Estados de existencias de producto obtenidos');
        }

        $productStockStatuses = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Product Stock Status List', $request->all(), $productStockStatuses);
        $metaData = [
            'current_page' => $productStockStatuses->currentPage(),
            'last_page' => $productStockStatuses->lastPage(),
            'per_page' => $productStockStatuses->perPage(),
            'total' => $productStockStatuses->total(),
            'from' => $productStockStatuses->firstItem(),
            'to' => $productStockStatuses->lastItem(),
        ];        
        return $this->success($productStockStatuses->items(), 'Estados de existencias de producto obtenidos', $metaData);
    }
}