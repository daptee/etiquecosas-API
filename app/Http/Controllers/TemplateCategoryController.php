<?php

namespace App\Http\Controllers;

use App\Models\TemplateCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class TemplateCategoryController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = TemplateCategory::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('name', 'asc');
        if (!$perPage) {
            $templateCategories = $query->get();
            $this->logAudit(Auth::user(), 'Get Template Category List', $request->all(), $templateCategories);
            return $this->success($templateCategories, 'Plantillas de categoria obtenidas');
        }

        $templateCategories = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Template Category List', $request->all(), $templateCategories);
        $metaData = [
            'current_page' => $templateCategories->currentPage(),
            'last_page' => $templateCategories->lastPage(),
            'per_page' => $templateCategories->perPage(),
            'total' => $templateCategories->total(),
            'from' => $templateCategories->firstItem(),
            'to' => $templateCategories->lastItem(),
        ];        
        return $this->success($templateCategories->items(), 'Plantillas de categoria obtenidas', $metaData);
    }
}
