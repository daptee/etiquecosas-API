<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCustomization;
use App\Models\ProductImage;
use App\Models\ProductWholesale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
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
            'categories:id',
            'costs:id',
            'attributes',
            'attributeValues',
            'variants',
            'customization',
            'wholesales',
            'relatedProducts:id',
            'images',
        ]);
        $this->logAudit(Auth::user(), 'Get Product Details', ['product_id' => $id], $product);
        return $this->success($product, 'Producto obtenido exitosamente');
    }

    protected function validateProductRequest(Request $request, ?int $productId = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($productId)], 
            'product_type_id' => 'required|integer|exists:product_types,id',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0|lt:price',
            'product_stock_status_id' => 'required|integer|exists:product_stock_statuses,id',
            'stock_quantity' => 'nullable|integer|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'wholesale_min_amount' => 'nullable|integer|min:0',
            'tag_id' => 'nullable|exists:configuration_tags,id',
            'costs' => 'nullable|array',
            'costs.*' => 'integer|exists:costs,id',
            'categories' => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
            'wholesales' => 'nullable|array',
            'wholesales.*.amount' => 'required_with:wholesales|numeric|min:0',
            'wholesales.*.discount' => 'required_with:wholesales|numeric|min:0',
            'description' => 'nullable|string',
            'shortDescription' => 'nullable|string|max:500',
            'shipping_text' => 'nullable|string',
            'shipping_time_text' => 'nullable|string',
            'notifications_text' => 'nullable|string',
            'tutorial_link' => 'nullable|url|max:2048',
            'is_customizable' => 'nullable|boolean',
            'is_feature' => 'nullable|boolean',
            'product_status_id' => 'required|integer|exists:product_statuses,id',
            'related_products' => 'nullable|array',
            'related_products.*' => 'integer|exists:products,id',
            'attributes' => 'nullable|array',
            'attributes.*' => 'integer|exists:attributes,id',
            'variants' => 'nullable|array',
            'variants.*.attributesvalues' => 'required|array',
            'variants.*.attributesvalues.*.attribute_id' => 'nullable|integer|exists:attributes,id',
            'variants.*.attributesvalues.*.id' => 'nullable|numeric', 
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.discounted_price' => 'nullable|numeric|min:0|lt:price',
            'variants.*.stock_status' => 'required|integer|exists:product_stock_statuses,id',
            'variants.*.stock_quantity' => 'nullable|integer|min:0',
            'variants.*.wholesale_price' => 'nullable|numeric|min:0',
            'variants.*.wholesale_min_amount' => 'nullable|integer|min:0',
            'meta_data' => 'nullable|json',
            'customization' => 'nullable|json',
            'images' => 'nullable|array',
            'images.*.img' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'main_image_index' => 'nullable|integer|min:0',
            'variants_image' => 'nullable|array',
            'variants_image.*.variant_id' => 'required|integer', 
            'variants_image.*.img' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Product Validation Fail (Initial)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $decodedData = $request->all();
        $jsonFieldsToDecode = ['meta_data', 'customization'];

        foreach ($jsonFieldsToDecode as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $decodedValue = json_decode($request->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decodedData[$field] = $decodedValue;
                } else {
                    $validator->errors()->add($field, 'El campo ' . $field . ' no es un JSON válido después de la decodificación.');
                    $this->logAudit(Auth::user(), 'Product Validation Fail (JSON Decode Error)', $request->all(), $validator->errors());
                    return $this->validationError($validator->errors());
                }
            }
        }

        $internalJsonRules = [
            'customization' => 'nullable|array',
            'customization.is_details_active' => 'nullable|integer|in:0,1',
            'customization.is_colors_active' => 'nullable|integer|in:0,1',
            'customization.is_icons_active' => 'nullable|integer|in:0,1',
            'customization.colors' => 'nullable|array',
            'customization.colors.*' => 'integer|exists:personalization_colors,id',
            'customization.icons' => 'nullable|array',
            'customization.icons.*' => 'integer|exists:personalization_icons,id',

            'meta_data' => 'nullable|array',
            'meta_data.title' => 'nullable|string|max:255',
            'meta_data.description' => 'nullable|string',
        ];

        $internalValidator = Validator::make($decodedData, $internalJsonRules);
        if ($internalValidator->fails()) {
            $this->logAudit(Auth::user(), 'Product Validation Fail (Internal JSON)', $decodedData, $internalValidator->errors());
            return $this->validationError($internalValidator->errors());
        }

        return null;
    }

    protected function prepareProductData(Request $request): array
    {
        $productData = $request->except([
            'categories',
            'variants',
            'images',
            'main_image_index',
            'variants_image',
            'costs',
            'attributes',
            'customization',
            'wholesales',
            'related_products',
        ]);
        $productData['is_feature'] = (bool)($request->input('is_feature', false));
        $productData['is_customizable'] = (bool)($request->input('is_customizable', false));

        $jsonFields = ['meta_data'];
        foreach ($jsonFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                if (is_string($value)) {
                    $decodedValue = json_decode($value, true);
                    $productData[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decodedValue : null;
                } else {
                    $productData[$field] = $value;
                }
            } else {
                $productData[$field] = null;
            }
        }
        return $productData;
    }

    protected function syncProductRelations(Product $product, Request $request)
    {
        if ($request->has('categories')) {
            $product->categories()->attach($request->categories);
        } else {
            $product->categories()->detach();
        }

        if ($request->has('costs')) {
            $product->costs()->sync($request->costs);
        } else {
            $product->costs()->detach();
        }

        if ($request->has('related_products')) {
            $product->relatedProducts()->sync($request->related_products);
        } else {
            $product->relatedProducts()->detach();
        }
        
        if ($request->has('attributes')) {
            $product->attributes()->sync($request->attributes);
        } else {
            $product->attributes()->detach();
        }
    }

    protected function createProductWholesales(Product $product, Request $request)
    {
        if ($request->has('wholesales') && is_array($request->input('wholesales'))) {
            $wholesalesData = $request->input('wholesales');
            $productWholesales = [];
            foreach ($wholesalesData as $wholesale) {
                if (!is_array($wholesale)) { 
                    Log::error("Wholesale data element is not an array: " . json_encode($wholesale));
                    throw ValidationException::withMessages(['wholesales' => 'Cada elemento de la venta al por mayor debe ser un objeto.']);
                }
                $productWholesales[] = new ProductWholesale([
                    'amount' => $wholesale['amount'],
                    'discount' => $wholesale['discount'],
                ]);
            }
            $product->wholesales()->saveMany($productWholesales);
        }
    }

    protected function createOrUpdateProductCustomization(Product $product, Request $request)
    {
        if ($request->has('customization')) {
            $customizationData = $request->input('customization');
            $decodedCustomization = json_decode($customizationData, true);
            if (!is_array($decodedCustomization)) { 
                 Log::error("Customization data is not a valid JSON object: " . json_encode($customizationData));
                 throw ValidationException::withMessages(['customization' => 'El formato de personalización no es un JSON válido.']);
            }
            $product->customization()->updateOrCreate(
                [],
                ['customization' => $decodedCustomization]
            );
        } else {
            $product->customization()->delete();
        }
    }

    protected function createProductImages(Product $product, Request $request)
    {
        $imagesCollection = collect($request->file('images'));

        if ($imagesCollection->isNotEmpty()) {
            foreach ($imagesCollection as $index => $imageArray) {
                $imageFile = (is_array($imageArray) && isset($imageArray['img'])) ? $imageArray['img'] : null;

                if ($imageFile instanceof \Illuminate\Http\UploadedFile && $imageFile->isValid()) {
                    $imageName = 'images/products/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                    Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile->getRealPath()));
                    ProductImage::create([
                        'product_id' => $product->id,
                        'img' => $imageName,
                        'is_main' => ((int)$request->input('main_image_index') === $index) ? 1 : 0,
                    ]);
                }
            }
        }
    }

    protected function createProductVariants(Product $product, Request $request): array
    {
        $variantDbIds = [];
        if ($request->has('variants') && is_array($request->input('variants'))) {
            $variantsArray = $request->input('variants'); 

            foreach ($variantsArray as $index => $variantData) {
                if (!is_array($variantData)) {
                    Log::error("createProductVariants: Variant data at index $index is not an array. Type: " . gettype($variantData) . " Value: " . json_encode($variantData));
                    throw ValidationException::withMessages([
                        "variants.$index" => ['Los datos de la variante no son válidos (cada variante debe ser un objeto). Este elemento es de tipo ' . gettype($variantData) . '.'],
                    ]);
                }

                $singleVariantValidator = Validator::make($variantData, [
                    'attributesvalues' => 'required|array',
                    'attributesvalues.*.id' => 'nullable|numeric',
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
                    throw ValidationException::withMessages([
                        "variants.$index" => $singleVariantValidator->errors()->all(),
                    ]);
                }

                $newVariant = ProductVariant::create([
                    'product_id' => $product->id,
                    'variant' => $variantData,
                    'sku' => $variantData['sku'] ?? null,
                    'price' => $variantData['price'],
                    'discounted_price' => $variantData['discounted_price'] ?? null,
                    'stock_status' => $variantData['stock_status'],
                    'stock_quantity' => $variantData['stock_quantity'] ?? null,
                    'wholesale_price' => $variantData['wholesale_price'] ?? null,
                    'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                ]);
                $variantDbIds[$index] = $newVariant->id;
            }
        }
        return $variantDbIds;
    }

    protected function createVariantImages(Product $product, Request $request, array $variantDbIds)
    {
        $variantsImagesCollection = collect($request->file('variants_image'));

        if ($variantsImagesCollection->isNotEmpty()) {
            foreach ($variantsImagesCollection as $index => $variantImageArray) {
                $imageFile = (is_array($variantImageArray) && isset($variantImageArray['img'])) ? $variantImageArray['img'] : null;
                $variantIdFromRequest = (is_array($variantImageArray) && isset($variantImageArray['variant_id'])) ? $variantImageArray['variant_id'] : null;
                $associatedVariantId = $variantDbIds[$variantIdFromRequest] ?? null;
                if ($imageFile instanceof \Illuminate\Http\UploadedFile && $imageFile->isValid() && $associatedVariantId) {
                    $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                    Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile->getRealPath()));
                    $variant = ProductVariant::find($associatedVariantId);
                    if ($variant) {
                        $variant->image_path = $imageName;
                        $variant->save();
                    }
                }
            }
        }
    }

    public function store(Request $request)
    {
        $validationResponse = $this->validateProductRequest($request);
        if ($validationResponse) {
            return $validationResponse;
        }
        DB::beginTransaction();
        $productData = $this->prepareProductData($request);
        $product = Product::create($productData);
        $this->syncProductRelations($product, $request);
        $variantDbIds = $this->createProductVariants($product, $request);
        $this->createProductWholesales($product, $request);
        $this->createOrUpdateProductCustomization($product, $request);
        $this->createProductImages($product, $request);
        $this->createVariantImages($product, $request, $variantDbIds);
        DB::commit();
        $product->load([
            'type',
            'status',
            'stockStatus',
            'categories',
            'variants',
            'tag',
            'images',
            'costs',
            'attributes',
            'customization',
            'wholesales',
            'relatedProducts',
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
            'tag_id' => 'nullable|exists:configuration_tags,id', 
            'description' => 'nullable|string',
            'is_feature' => 'nullable|boolean',
            'tutorial_link' => 'nullable|url|max:2048',
            'is_customizable' => 'nullable|boolean',
            'meta_data' => 'nullable|json',
            'meta_data.title' => 'nullable|string|max:255',
            'meta_data.description' => 'nullable|string',
            'wholesale_price' => 'nullable|numeric|min:0',
            'wholesale_min_amount' => 'nullable|integer|min:0',
            'shortDescription' => 'nullable|string|max:500',
            'shipping_text' => 'nullable|string',
            'shipping_time_text' => 'nullable|string',
            'notifications_text' => 'nullable|string',
            'related_products' => 'nullable|json',
            'wholesales' => 'nullable|json',
            'costs' => 'nullable|array',
            'costs.*' => 'integer|exists:costs,id',
            'customization' => 'nullable|json',
            'customization.is_details_active' => 'nullable|integer|in:0,1',
            'customization.is_colors_active' => 'nullable|integer|in:0,1',
            'customization.is_icons_active' => 'nullable|integer|in:0,1',
            'customization.colors' => 'nullable|array',
            'customization.colors.*' => 'integer|exists:personalization_colors,id',
            'customization.icons' => 'nullable|array',
            'customization.icons.*' => 'integer|exists:personalization_icons,id',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'exists:product_images,id',
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'main_image_id' => 'nullable|exists:product_images,id',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.attributesvalues' => 'required|array',
            'variants.*.attributesvalues.*.attribute_id' => 'required|integer|exists:attributes,id',
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.discounted_price' => 'nullable|numeric|min:0|lt:variants.*.price',
            'variants.*.stock_status' => 'required|integer|exists:product_stock_statuses,id',
            'variants.*.stock_quantity' => 'nullable|integer|min:0',
            'variants.*.wholesale_price' => 'nullable|numeric|min:0',
            'variants.*.wholesale_min_amount' => 'nullable|integer|min:0',
            'variants_image' => 'nullable|array',
            'variants_image.*.img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'variants_image.*.variant_id' => 'required|integer', 
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Product Validation Fail', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $productData = $request->except([
            'categories',
            'variants',
            'customization', 
            'images_to_delete',
            'new_images',
            'main_image_id',
            'costs', 
            'variants_image',
        ]);
        $productData['is_feature'] = $productData['is_feature'] ?? false;
        $productData['is_customizable'] = $productData['is_customizable'] ?? false;
        $jsonFields = ['meta_data', 'wholesales', 'related_products', 'customization'];
        foreach ($jsonFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                if (is_string($value)) {
                    $decodedValue = json_decode($value, true);
                    $productData[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decodedValue : null;
                } else {
                    $productData[$field] = $value;
                }
            } else {
                $productData[$field] = null;
            }
        }

        $product->update($productData);        
        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        } else {
            $product->categories()->detach();
        }

        if ($request->has('costs')) {
            $product->costs()->sync($request->costs);
        } else {
            $product->costs()->detach();
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
            $variantDataForColumn = $variantParsed;

            if (isset($variantData['id']) && in_array($variantData['id'], $existingVariantIds)) {
                $productVariant = ProductVariant::where('id', $variantData['id'])->first();
                if ($productVariant) {
                    $productVariant->update([
                        'sku' => $variantData['sku'] ?? null,
                        'price' => $variantData['price'],
                        'discounted_price' => $variantData['discounted_price'] ?? null,
                        'stock_status' => $variantData['stock_status'],
                        'stock_quantity' => $variantData['stock_quantity'] ?? null,
                        'wholesale_price' => $variantData['wholesale_price'] ?? null,
                        'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                        'variant' => ['attributesvalues' => $variantData['attributesvalues']] 
                    ]);
                }
            } else {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantData['sku'] ?? null,
                    'price' => $variantData['price'],
                    'discounted_price' => $variantData['discounted_price'] ?? null,
                    'stock_status' => $variantData['stock_status'],
                    'stock_quantity' => $variantData['stock_quantity'] ?? null,
                    'wholesale_price' => $variantData['wholesale_price'] ?? null,
                    'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                    'variant' => ['attributesvalues' => $variantData['attributesvalues']],
                ]);
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
                        Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile->getRealPath()));

                        $variantToUpdate->img = $imageName;
                        $variantToUpdate->save();
                    }
                }
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
            if ($product->images()->where('id', $mainImageId)->exists()) {
                $product->images()->update(['is_main' => false]);
                ProductImage::where('id', $mainImageId)->update(['is_main' => true]);
            }
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
            'customization',
            'tag',
            'images',
            'costs',
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
