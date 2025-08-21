<?php

namespace App\Http\Controllers;

use App\Models\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ProductStatusController extends Controller
{
    use FindObject, ApiResponse, Auditable;
     
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = ProductStatus::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('name', 'asc');
        if (!$perPage) {
            $productStatuses = $query->get();
            $this->logAudit(Auth::user(), 'Get Product Status List', $request->all(), $productStatuses);
            return $this->success($productStatuses, 'Estados de producto obtenidos');
        }

        $productStatuses = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Product Status List', $request->all(), $productStatuses);
        $metaData = [
            'current_page' => $productStatuses->currentPage(),
            'last_page' => $productStatuses->lastPage(),
            'per_page' => $productStatuses->perPage(),
            'total' => $productStatuses->total(),
            'from' => $productStatuses->firstItem(),
            'to' => $productStatuses->lastItem(),
        ];        
        return $this->success($productStatuses->items(), 'Estados de producto obtenidos', $metaData);
    }
}
