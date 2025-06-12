<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class CategoryController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
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

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $categories = $query->get();
            $this->logAudit(Auth::user(), 'Get Categories List', $request->all(), $categories);
            return $this->success($categories, 'Categorias obtenidas');
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
        return $this->success($categories->items(), 'Categorias obtenidas', $metaData);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories',
            'categoryId' => 'nullable|exists:categories,id',
            'img' => 'nullable|image|max:2048',
            'icon' => 'nullable|file|mimes:svg|max:2048',
            'color' => 'nullable|string|max:50',
            'metaData' => 'nullable|json',
            'description' => 'nullable|string',
            'banner' => 'nullable|image|max:2048',
            'statusId' => 'nullable|in:1,2',
            'tagId' => 'nullable|exists:configuration_tags,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Category', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $imgPath = null;
        if ($request->hasFile('img')) {
            $imageFile = $request->file('img');
            $imageName = 'images/categories/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
            Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));
            $imgPath = $imageName;
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconFile = $request->file('icon');
            $iconName = 'icons/categories/' . uniqid('icon_') . '.' . $iconFile->getClientOriginalExtension();
            Storage::disk('public_uploads')->put($iconName, file_get_contents($iconFile));
            $iconPath = $iconName;
        }

        $bannerPath = null;
        if ($request->hasFile('banner')) {
            $bannerFile = $request->file('banner');
            $bannerName = 'banners/categories/' . uniqid('banner_') . '.' . $bannerFile->getClientOriginalExtension();
            Storage::disk('public_uploads')->put($bannerName, file_get_contents($bannerFile));
            $bannerPath = $bannerName;
        }

        $category = Category::create([
            'name' => $request->name,
            'category_id' => $request->categoryId,
            'img' => $imgPath,
            'icon' => $iconPath,
            'color' => $request->color,
            'meta_data' => $request->metaData,
            'description' => $request->description,
            'banner' => $bannerPath,
            'status_id' => $request->statusId ?? 1,
            'tag_id' => $request->tagId,
        ]);
        $this->logAudit(Auth::user(), 'Store Category', $request->all(), $category);        
        if ($category->img) {
            $category->img = asset($category->img);
        }

        if ($category->icon) {
            $category->icon = asset($category->icon);
        }

        if ($category->banner) {
            $category->banner = asset($category->banner);
        }

        return $this->success($category, 'Categoría creada', 201);
    }

    public function update(Request $request, $id)
    {
        $category = $this->findObject(Category::class, $id);
        foreach (['tagId', 'categoryId'] as $key) {
            if ($request->has($key) && in_array($request->input($key), ['null', ''])) {
                $request->merge([$key => null]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->ignore($category->id),
            ],
            'categoryId' => 'nullable|exists:categories,id',
            'img' => 'nullable|image|max:2048',
            'icon' => 'nullable|file|mimes:svg|max:2048',
            'color' => 'nullable|string|max:50',
            'metaData' => 'nullable|json',
            'description' => 'nullable|string',
            'banner' => 'nullable|image|max:2048',
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

        $imgPath = $category->img;
        if ($request->hasFile('img')) {
            $imageFile = $request->file('img');
            $imageName = 'images/categories/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
            if (Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile))) {
                if ($category->img && Storage::disk('public_uploads')->exists($category->img)) {
                    Storage::disk('public_uploads')->delete($category->img);
                }
                $imgPath = $imageName;
            }
        } elseif ($request->input('img') === 'null' || $request->input('img') === '') {
            if ($category->img && Storage::disk('public_uploads')->exists($category->img)) {
                Storage::disk('public_uploads')->delete($category->img);
            }
            $imgPath = null;
        }

        $iconPath = $category->icon;
        if ($request->hasFile('icon')) {
            $iconFile = $request->file('icon');
            $iconName = 'icons/categories/' . uniqid('icon_') . '.' . $iconFile->getClientOriginalExtension();
            if (Storage::disk('public_uploads')->put($iconName, file_get_contents($iconFile))) {
                if ($category->icon && Storage::disk('public_uploads')->exists($category->icon)) {
                    Storage::disk('public_uploads')->delete($category->icon);
                }
                $iconPath = $iconName;
            }
        } elseif ($request->input('icon') === 'null' || $request->input('icon') === '') {
            if ($category->icon && Storage::disk('public_uploads')->exists($category->icon)) {
                Storage::disk('public_uploads')->delete($category->icon);
            }
            $iconPath = null;
        }

        $bannerPath = $category->banner;
        if ($request->hasFile('banner')) {
            $bannerFile = $request->file('banner');
            $bannerName = 'banners/categories/' . uniqid('banner_') . '.' . $bannerFile->getClientOriginalExtension();
            if (Storage::disk('public_uploads')->put($bannerName, file_get_contents($bannerFile))) {
                if ($category->banner && Storage::disk('public_uploads')->exists($category->banner)) {
                    Storage::disk('public_uploads')->delete($category->banner);
                }
                $bannerPath = $bannerName;
            }
        } elseif ($request->input('banner') === 'null' || $request->input('banner') === '') {
            if ($category->banner && Storage::disk('public_uploads')->exists($category->banner)) {
                Storage::disk('public_uploads')->delete($category->banner);
            }
            $bannerPath = null;
        }

        $category->name = $request->input('name', $category->name);
        $category->category_id = $request->input('categoryId', $category->category_id);
        $category->img = $imgPath;
        $category->icon = $iconPath;
        $category->color = $request->input('color', $category->color);
        $category->meta_data = $request->input('metaData', $category->meta_data);
        $category->description = $request->input('description', $category->description);
        $category->banner = $bannerPath;
        $category->status_id = $request->input('statusId', $category->status_id);
        $category->tag_id = $request->input('tagId', $category->tag_id);        
        $category->save();
        if ($category->img) {
            $category->img = asset($category->img);
        }

        if ($category->icon) {
            $category->icon = asset($category->icon);
        }

        if ($category->banner) {
            $category->banner = asset($category->banner);
        }

        $this->logAudit(Auth::user(), 'Update Category', $request->all(), $category);
        return $this->success($category, 'Categoría actualizada');
    }

    public function delete($id)
    {
        $category = $this->findObject(Category::class, $id);
        $category->delete();
        $this->logAudit(Auth::user(), 'Delete Category', $id, $category);
        return $this->success($category, 'Categoría eliminada');
    }
}
