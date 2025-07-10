<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCustomization;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ProductController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $query = Product::query()
            ->select([
                'id',
                'name',
                'sku',
                'price',
                'discounted_price',
                'tag_id',
                'meta_data',
                'is_feature',
                'is_customizable',
                'product_type_id',
                'product_status_id',
                'product_stock_status_id',
            ])
            ->with([
                'type:id,name',
                'status:id,name',
                'stockStatus:id,name',
                'categories:id,name',
            ]);
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('status_id')) {
            $query->where('product_status_id', $request->query('status_id'));
        }

        if ($request->has('category_id')) {
            $categoryId = $request->query('category_id');
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($request->has('stock_status_id')) {
            $query->where('product_stock_status_id', $request->query('stock_status_id'));
        }

        if ($request->has('tag_id')) {
            $query->where('tag_id', $request->query('tag_id'));
        }

        if ($request->has('product_type_id')) {
            $query->where('product_type_id', $request->query('product_type_id'));
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $products = $query->get();
            $this->logAudit(Auth::user(), 'Get Product List', $request->all(), $products);
            return $this->success($products, 'Productos obtenidos');
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Product List', $request->all(), $products->items());
        $metaData = [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'from' => $products->firstItem(),
            'to' => $products->lastItem(),
        ];
        return $this->success($products->items(), 'Productos obtenidos', $metaData);
    }

    public function show(Request $request, string $id)
    {
        $product = $this->findObject(Product::class, $id);
        $product->load([
            'type',
            'status',
            'stockStatus',
            'categories',
            'costs',
            'attributes',
            'attributeValues',
            'variants',
            'customizations',
        ]);
        $this->logAudit(Auth::user(), 'Get Product Details', ['product_id' => $id], $product);
        return $this->success($product, 'Producto obtenido exitosamente');
    }

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'categories' => 'required|array|min:1',
        'categories.*' => 'exists:categories,id',
        'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')],
        'discounted_price' => 'nullable|numeric|min:0|lt:price',
        'stock_quantity' => 'nullable|integer|min:0',
        'tag_id' => 'nullable|exists:tags,id',
        'description' => 'nullable|string',
        'is_feature' => 'nullable|boolean',
        'tutorial_link' => 'nullable|url|max:2048',
        'is_customizable' => 'nullable|boolean',
        'meta_data' => 'nullable|json',
        'images' => 'nullable|array',
        'images.*.img' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'main_image_index' => 'nullable|integer|min:0',
        'variants' => 'nullable|json',
        'variants_image' => 'nullable|array',
        'variants_image.*.img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'variants_image.*.variant_id' => 'required|integer',
        'customizations' => 'nullable|json',
        'product_type_id' => 'required|integer|exists:product_types,id',
        'price' => 'required|numeric|min:0',
        'product_stock_status_id' => 'required|integer|exists:product_stock_statuses,id',
        'wholesale_price' => 'nullable|numeric|min:0',
        'wholesale_min_amount' => 'nullable|integer|min:0',
        'costs' => 'nullable|json',
        'wholesales' => 'nullable|json',
        'shortDescription' => 'nullable|string|max:500',
        'shipping_text' => 'nullable|string',
        'shipping_time_text' => 'nullable|string',
        'notifications_text' => 'nullable|string',
        'product_status_id' => 'required|integer|exists:product_statuses,id',
        'related_products' => 'nullable|json',
    ]);

    if ($validator->fails()) {
        $this->logAudit(Auth::user(), 'Store Product Validation Fail', $request->all(), $validator->errors());
        return $this->validationError($validator->errors());
    }

    $productData = $request->except(['categories', 'variants', 'customizations', 'images', 'main_image_index', 'variants_image']);
    $productData['is_feature'] = $productData['is_feature'] ?? false;
    $productData['is_customizable'] = $productData['is_customizable'] ?? false;

    $jsonFields = ['meta_data', 'costs', 'wholesales', 'related_products'];
    foreach ($jsonFields as $field) {
        if ($request->has($field) && is_string($request->input($field))) {
            $productData[$field] = json_decode($request->input($field), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $productData[$field] = null;
            }
        } elseif (!$request->has($field)) {
            $productData[$field] = null;
        }
    }

    $product = Product::create($productData);

    if ($request->has('categories')) {
        $product->categories()->attach($request->categories);
    }

    $variantDbIds = [];

    if ($request->has('variants')) {
        $variantsArray = json_decode($request->input('variants'), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($variantsArray)) {
            foreach ($variantsArray as $index => $variantData) {
                $singleVariantValidator = Validator::make($variantData, [
                    'attributesvalues' => 'required|array',
                    'attributesvalues.*.attribute_id' => 'required|integer|exists:attributes,id',
                    'sku' => ['nullable', 'string', 'max:255'],
                    'price' => 'required|numeric|min:0',
                    'discounted_price' => 'nullable|numeric|min:0|lt:price',
                    'stock_status' => 'required|integer|exists:product_stock_statuses,id',
                    'stock_quantity' => 'nullable|integer|min:0',
                    'wholesale_price' => 'nullable|numeric|min:0',
                    'wholesale_min_amount' => 'nullable|integer|min:0',
                ]);

                if ($singleVariantValidator->fails()) {
                    return $this->validationError([
                        "variants.$index" => $singleVariantValidator->errors()
                    ]);
                }

                $newVariant = ProductVariant::create([
                    'product_id' => $product->id,
                    'variant' => $variantData,
                ]);

                $variantDbIds[$index] = $newVariant->id;
            }
        }
    }

  if ($request->hasFile('variants_image')) {
    foreach ($request->file('variants_image') as $imgData) {
        if (
            isset($imgData['img']) && $imgData['img']->isValid() &&
            isset($imgData['variant_id'])
        ) {
            $imageFile = $imgData['img'];
            $variantId = $imgData['variant_id'];

            $variantToUpdate = ProductVariant::find($variantId);
            if ($variantToUpdate) {
                $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));

                $variantToUpdate->img = $imageName;
                $variantToUpdate->save();
            }
        }
    }
}
    if ($request->has('customizations')) {
        $customizationsArray = json_decode($request->input('customizations'), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($customizationsArray)) {
            ProductCustomization::create([
                'product_id' => $product->id,
                'customization' => $customizationsArray,
            ]);
        }
    }

    if ($request->hasFile('images')) {
        $mainImageIndex = $request->input('main_image_index');
        foreach ($request->file('images') as $index => $imageArray) {
            $imageFile = $imageArray['img'];
            if ($imageFile->isValid()) {
                $imageName = 'images/products/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));
                ProductImage::create([
                    'product_id' => $product->id,
                    'img' => $imageName,
                    'is_main' => ($index == $mainImageIndex),
                ]);
            }
        }
    }

    $product->load([
        'type',
        'status',
        'stockStatus',
        'categories',
        'variants',
        'customization',
        'tag',
        'images',
    ]);

    $this->logAudit(Auth::user(), 'Product Created', $request->all(), $product);
    return $this->success($product, 'Producto creado exitosamente', 201);
}


    public function update(Request $request, string $id)
    {
        $product = $this->findObject(Product::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'product_type_id' => 'required|exists:product_types,id',
            'product_status_id' => 'required|exists:product_statuses,id',
            'price' => 'required|numeric|min:0',
            'product_stock_status_id' => 'required|exists:product_stock_statuses,id',
            'categories' => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($product->id),
            ],
            'discounted_price' => 'nullable|numeric|min:0|lt:price',
            'stock_quantity' => 'nullable|integer|min:0',
            'tag_id' => 'nullable|exists:tags,id',
            'description' => 'nullable|string',
            'is_feature' => 'nullable|boolean',
            'tutorial_link' => 'nullable|url|max:2048',
            'is_customizable' => 'nullable|boolean',
            'meta_data' => 'nullable|json',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'exists:product_images,id',
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'main_image_id' => 'nullable|exists:product_images,id',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.variant' => 'required|json',
            'customizations' => 'nullable|array',
            'customizations.*.id' => 'nullable|exists:product_customizations,id',
            'customizations.*.customization' => 'required|json',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Product Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $productData = $request->except([
            'categories',
            'variants',
            'customizations',
            'images_to_delete',
            'new_images',
            'main_image_id',
        ]);
        $productData['is_feature'] = $productData['is_feature'] ?? false;
        $productData['is_customizable'] = $productData['is_customizable'] ?? false;
        if ($request->has('meta_data') && is_string($request->input('meta_data'))) {
            $productData['meta_data'] = json_decode($request->input('meta_data'), true);
        } elseif (!$request->has('meta_data')) {
            $productData['meta_data'] = null;
        }

        $product->update($productData);
        $existingCategoryIds = $product->categories()->pluck('categories.id')->toArray();
        $incomingCategoryIds = $request->input('categories', []);
        $toAttach = array_diff($incomingCategoryIds, $existingCategoryIds);
        $toDetach = array_diff($existingCategoryIds, $incomingCategoryIds);
        if (!empty($toDetach)) {
            $product->categories()->detach($toDetach);
        }
        if (!empty($toAttach)) {
            $product->categories()->attach($toAttach);
        }

        $existingVariantIds = $product->variants()->pluck('id')->toArray();
        $incomingVariants = collect($request->input('variants', []));
        $incomingVariantIds = $incomingVariants->pluck('id')->filter()->toArray();
        $toDeleteVariants = array_diff($existingVariantIds, $incomingVariantIds);
        if (!empty($toDeleteVariants)) {
            ProductVariant::whereIn('id', $toDeleteVariants)->delete();
        }

        foreach ($incomingVariants as $variantData) {
            $variantParsed = isset($variantData['variant']) && is_string($variantData['variant'])
                             ? json_decode($variantData['variant'], true)
                             : ($variantData['variant'] ?? []);

            if (isset($variantData['id']) && in_array($variantData['id'], $existingVariantIds)) {
                ProductVariant::where('id', $variantData['id'])->update([
                    'variant' => $variantParsed,
                ]);
            } else {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'variant' => $variantParsed,
                ]);
            }
        }

        $existingCustomizationIds = $product->customizations()->pluck('id')->toArray();
        $incomingCustomizations = collect($request->input('customizations', []));
        $incomingCustomizationIds = $incomingCustomizations->pluck('id')->filter()->toArray();
        $toDeleteCustomizations = array_diff($existingCustomizationIds, $incomingCustomizationIds);
        if (!empty($toDeleteCustomizations)) {
            ProductCustomization::whereIn('id', $toDeleteCustomizations)->delete();
        }

        foreach ($incomingCustomizations as $customizationData) {
            $customizationParsed = isset($customizationData['customization']) && is_string($customizationData['customization'])
                                 ? json_decode($customizationData['customization'], true)
                                 : ($customizationData['customization'] ?? []);

            if (isset($customizationData['id']) && in_array($customizationData['id'], $existingCustomizationIds)) {
                ProductCustomization::where('id', $customizationData['id'])->update([
                    'customization' => $customizationParsed,
                ]);
            } else {
                ProductCustomization::create([
                    'product_id' => $product->id,
                    'customization' => $customizationParsed,
                ]);
            }
        }

        if ($request->has('images_to_delete')) {
            $imageIdsToDelete = $request->input('images_to_delete');
            $imagesToDelete = ProductImage::whereIn('id', $imageIdsToDelete)->get();
            foreach ($imagesToDelete as $img) {
                Storage::disk('public_uploads')->delete($img->img);
                $img->delete();
            }
        }

        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $imageFile) {
                if ($imageFile->isValid()) {
                    $imageName = 'images/products/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                    Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile->getRealPath()));
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'img' => $imageName,
                        'is_main' => false,
                    ]);
                }
            }
        }

        if ($request->has('main_image_id')) {
            $mainImageId = $request->input('main_image_id');
            $product->images()->update(['is_main' => false]);
            ProductImage::where('id', $mainImageId)->update(['is_main' => true]);
        } else {
            if ($product->images()->where('is_main', true)->doesntExist()) {
                $firstImage = $product->images()->first();
                if ($firstImage) {
                    $firstImage->update(['is_main' => true]);
                }
            }
        }

        $product->load([
            'type',
            'status',
            'stockStatus',
            'categories',
            'variants',
            'customizations',
            'tag',
            'images',
        ]);
        $this->logAudit(Auth::user(), 'Product Updated', $request->all(), $product);
        return $this->success($product, 'Producto actualizado exitosamente');
    }

    public function delete($id)
    {
        $product = $this->findObject(Product::class, $id);
        $product->delete();
        $this->logAudit(Auth::user(), 'Delete Product', $id, $product);
        return $this->success($product, 'Product eliminado');
    }
}
