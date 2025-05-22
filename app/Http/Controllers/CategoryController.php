<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class CategoryController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $categoryId = $request->query('categoryId');
        $query = Category::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('statusId', $statusId);
        }

        if ($categoryId) {
            $query->where('cagetoryId', $categoryId);
        }

        $categories = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Categories List', $request->all(), $categories);
        $metaData = [
            'current_page' => $categories->currentPage(),
            'last_page' => $categories->lastPage(),
            'per_page' => $categories->perPage(),
            'total' => $categories->total(),
            'from' => $categories->firstItem(),
            'to' => $categories->lastItem(),
        ];
        return response()->json([
            'message' => 'Categorias obtenidas',
            'data' => $categories->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories',
            'categoryId' => 'nullable|exists:categories,id',
            'img' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'statusId' => 'nullable|in:1,2',
            'tagId' => 'nullable|exists:configuration_tags,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Category', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $category = Category::create([
            'name' => $request->name,
            'category_id' => $request->categoryId,
            'img' => $request->img,
            'icon' => $request->icon,
            'color' => $request->color,
            'statusId' => $request->statusId ?? 1,
            'tag_id' => $request->tagId,
        ]);
        $this->logAudit(Auth::user(), 'Store Category', $request->all(), $category);
        return $this->success($category, 'Categoría creada', 201);
    }

    public function update(Request $request, $id)
    {
        $category = $this->findObject(Category::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'categoryId' => 'nullable|exists:categories,id',
            'img' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:50',
            'statusId' => 'nullable|in:1,2',
            'tagId' => 'nullable|exists:configuration_tags,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Category', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        if ($request->categoryId && $request->categoryId == $category->id) {
            $error = ['categoryId' => ['La categoría no puede ser su propia categoría padre.']];
            $this->logAudit(Auth::user(), 'Update Category', $request->all(), $error);
            return $this->validationError($error);
        }

        $category->name = $request->input('name', $category->name);
        $category->category_id = $request->input('categoryId', $category->category_id);
        $category->img = $request->input('img', $category->img);
        $category->icon = $request->input('icon', $category->icon);
        $category->color = $request->input('color', $category->color);
        $category->status_id = $request->input('statusId', $category->status_id);
        $category->tag_id = $request->input('tagId', $category->tagId);
        $category->save();
        $this->logAudit(Auth::user(), 'Update Category', $request->all(), $category);
        return $this->success($category, 'Categoría actualizada');
    }
}
