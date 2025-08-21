<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ProductTypeController extends Controller
{
    use FindObject, ApiResponse, Auditable;
     
    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = ProductType::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('name', 'asc');
        if (!$perPage) {
            $productTypes = $query->get();
            $this->logAudit(Auth::user(), 'Get Product Type List', $request->all(), $productTypes);
            return $this->success($productTypes, 'Tipos de producto obtenidos');
        }

        $productTypes = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Product Type List', $request->all(), $productTypes);
        $metaData = [
            'current_page' => $productTypes->currentPage(),
            'last_page' => $productTypes->lastPage(),
            'per_page' => $productTypes->perPage(),
            'total' => $productTypes->total(),
            'from' => $productTypes->firstItem(),
            'to' => $productTypes->lastItem(),
        ];        
        return $this->success($productTypes->items(), 'Tipos de producto obtenidos', $metaData);
    }
}
