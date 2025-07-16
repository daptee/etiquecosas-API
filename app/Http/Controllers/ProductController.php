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
            'wholesales.*.id' => 'nullable|integer|exists:product_wholesales,id',
            'description' => 'nullable|string',
            'shortDescription' => 'nullable|string|max:500',
            'shipping_text' => 'nullable|string',
            'shipping_time_text' => 'nullable|string',
            'notifications_text' => 'nullable|string',
            'tutorial_link' => 'nullable|url|max:2048',
            'variants' => 'nullable|json',
            'is_customizable' => 'nullable|boolean',
            'customization' => 'nullable|json', 
            'meta_data' => 'nullable|json',
            'is_feature' => 'nullable|boolean',
            'product_status_id' => 'required|integer|exists:product_statuses,id',
            'images' => 'nullable|array',
            'images.*.img' => 'required_with:images.*.id|image|mimes:jpeg,png,jpg,gif,svg|max:2048', 
            'images.*.id' => 'nullable|integer|exists:product_images,id', 
            'main_image_index' => 'nullable|integer|min:0',
            'related_products' => 'nullable|array',
            'related_products.*' => 'integer|exists:products,id',
            'attributes' => 'nullable|array',
            'attributes.*' => 'integer|exists:attributes,id',
            'variants_image' => 'nullable|array',
            'variants_image.*.img' => 'required_with:variants_image.*.variant_id|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'variants_image.*.variant_id' => 'required|integer|exists:product_variants,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Product Validation Fail (Initial)', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $decodedData = $request->all();
        $jsonFieldsToDecode = ['variants', 'customization', 'meta_data'];
        foreach ($jsonFieldsToDecode as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $decodedValue = json_decode($request->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decodedData[$field] = $decodedValue;
                } else {
                    $validator->errors()->add($field, 'El campo ' . $field . ' no es un JSON válido.');
                    $this->logAudit(Auth::user(), 'Product Validation Fail (JSON Decode)', $request->all(), $validator->errors());
                    return $this->validationError($validator->errors());
                }
            }
        }

        $internalJsonRules = [
            'variants' => 'nullable|array',
            'variants.*.attributesvalues' => 'required|array',
            'variants.*.attributesvalues.*.attribute_id' => 'required|integer|exists:attributes,id',
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.discounted_price' => 'nullable|numeric|min:0|lt:price',
            'variants.*.stock_status' => 'required|integer|exists:product_stock_statuses,id',
            'variants.*.stock_quantity' => 'nullable|integer|min:0',
            'variants.*.wholesale_price' => 'nullable|numeric|min:0',
            'variants.*.wholesale_min_amount' => 'nullable|integer|min:0',
            'variants.*.id' => 'nullable|integer|exists:product_variants,id',
            'customization' => 'nullable|array',
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
            'wholesales',       
            'customization',
            'related_products', 
        ]);

        $productData['is_feature'] = (bool)($request->input('is_feature', false));
        $productData['is_customizable'] = (bool)($request->input('is_customizable', false));
        $jsonFieldsToProcess = ['meta_data'];
        foreach ($jsonFieldsToProcess as $field) {
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
            $product->categories()->sync($request->categories);
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
    }

    protected function createProductWholesales(Product $product, Request $request)
    {
        if ($request->has('wholesales') && is_array($request->input('wholesales'))) {
            $wholesalesData = $request->input('wholesales');
            $productWholesales = [];
            foreach ($wholesalesData as $wholesale) {
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
       foreach ($request->file('images') as $index => $imageArray) {
                $imageFile = $imageArray['img'] ?? null;
                if ($imageFile && $imageFile->isValid()) {
                    $imageName = 'images/products/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                    Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));
                    ProductImage::create([
                        'product_id' => $product->id,
                        'img' => $imageName,
                        'is_main' => ($request->input('main_image_index') == $index) ? 1 : 0,
                    ]);
                }
            }    
    }

    protected function createProductVariants(Product $product, Request $request): array
    {
        $variantDbIds = [];
        if ($request->has('variants')) {
            $variantsData = $request->input('variants');
            $variantsArray = [];

            if (is_string($variantsData)) {
                $decodedVariants = json_decode($variantsData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedVariants)) {
                    $variantsArray = $decodedVariants;
                } else {
                    return [];
                }
            } elseif (is_array($variantsData)) {
                $variantsArray = $variantsData;
            } else {
                return []; 
            }

            foreach ($variantsArray as $index => $variantData) {
                $singleVariantValidator = Validator::make($variantData, [
                    'attributesvalues' => 'required|array',
                    'attributesvalues.*.id' => 'required|numeric',
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
                    'sku' => $variantData['sku'] ?? null,
                    'price' => $variantData['price'],
                ]);
                $variantDbIds[$index] = $newVariant->id;
            }
        }
        return $variantDbIds;
    }

    protected function createVariantImages(Product $product, Request $request, array $variantDbIds)
    {
        foreach ($request->file('variants_image') as $index => $variantImageArray) {
                $imageFile = $variantImageArray['img'] ?? null;
                $associatedVariantId = $variantDbIds[$index] ?? null;
                if ($imageFile && $imageFile->isValid() && $associatedVariantId) {
                    $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                    Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));                    
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

    protected function updateProductVariants(Product $product, Request $request): array
    {
        $currentVariantIds = $product->variants->pluck('id')->toArray();
        $incomingVariantIds = [];
        $variantDbIdsMap = [];
        if ($request->has('variants')) {
            $variantsJsonString = $request->input('variants');
            $variantsArray = json_decode($variantsJsonString, true);
            if (is_array($variantsArray)) {
                foreach ($variantsArray as $index => $variantData) {
                    $variantId = $variantData['id'] ?? null;
                    $singleVariantValidator = Validator::make($variantData, [
                        'id' => 'nullable|integer|exists:product_variants,id',
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
                        return $this->validationError(["variants.$index" => $singleVariantValidator->errors()]);
                    }

                    if ($variantId && in_array($variantId, $currentVariantIds)) {
                        $variant = ProductVariant::find($variantId);
                        if ($variant) {
                            $variant->update([
                                'variant' => $variantData,
                                'sku' => $variantData['sku'] ?? null,
                                'price' => $variantData['price'],
                                'discounted_price' => $variantData['discounted_price'] ?? null,
                                'stock_status' => $variantData['stock_status'],
                                'stock_quantity' => $variantData['stock_quantity'] ?? null,
                                'wholesale_price' => $variantData['wholesale_price'] ?? null,
                                'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                            ]);
                            $incomingVariantIds[] = $variantId;
                            $variantDbIdsMap[$index] = $variantId;
                        }
                    } else {
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
                        $incomingVariantIds[] = $newVariant->id;
                        $variantDbIdsMap[$index] = $newVariant->id;
                    }
                }
            }
        }

        $variantsToDelete = array_diff($currentVariantIds, $incomingVariantIds);
        if (!empty($variantsToDelete)) {
            ProductVariant::destroy($variantsToDelete);
        }

        return $variantDbIdsMap;
    }    

    protected function updateProductImages(Product $product, Request $request)
    {
        $currentImageIds = $product->images->pluck('id')->toArray();
        $incomingImageIds = [];
        $mainImageIndex = (int) $request->input('main_image_index', 0);
        $mainImageCandidateId = null;
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $imageArray) {
                $imageFile = $imageArray['img'] ?? null;
                $imageId = $imageArray['id'] ?? null;
                if ($imageFile && $imageFile->isValid()) {
                    $imageName = 'images/products/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                    Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));
                    if ($imageId && in_array($imageId, $currentImageIds)) {
                        $image = ProductImage::find($imageId);
                        if ($image) {
                            if ($image->img && Storage::disk('public_uploads')->exists($image->img)) {
                                Storage::disk('public_uploads')->delete($image->img);
                            }
                            $image->update(['img' => $imageName]);
                            $incomingImageIds[] = $imageId;
                        }
                    } else {
                        $newImage = ProductImage::create([
                            'product_id' => $product->id,
                            'img' => $imageName,
                            'is_main' => 0,
                        ]);
                        $incomingImageIds[] = $newImage->id;
                    }
                } elseif ($imageId && in_array($imageId, $currentImageIds)) {
                    $incomingImageIds[] = $imageId;
                }

                $processedImageId = $imageId ?? (isset($incomingImageIds[count($incomingImageIds)-1]) ? $incomingImageIds[count($incomingImageIds)-1] : null);
                if ($mainImageIndex == $index && $processedImageId) {
                    $mainImageCandidateId = $processedImageId;
                }
            }
        }

        $imagesToDelete = array_diff($currentImageIds, $incomingImageIds);
        if (!empty($imagesToDelete)) {
            $deletedImageFiles = ProductImage::whereIn('id', $imagesToDelete)->pluck('img')->toArray();
            ProductImage::destroy($imagesToDelete);
            foreach ($deletedImageFiles as $filePath) {
                if ($filePath && Storage::disk('public_uploads')->exists($filePath)) {
                    Storage::disk('public_uploads')->delete($filePath);
                }
            }
        }

        ProductImage::where('product_id', $product->id)->update(['is_main' => 0]);
        if ($mainImageCandidateId) {
            ProductImage::where('id', $mainImageCandidateId)->update(['is_main' => 1]);
        } elseif (!empty($incomingImageIds)) {
            ProductImage::where('id', $incomingImageIds[0])->update(['is_main' => 1]);
        } elseif (empty($incomingImageIds) && $product->images()->count() > 0) {
            $firstRemainingImage = $product->images()->first();
            if ($firstRemainingImage) {
                $firstRemainingImage->update(['is_main' => 1]);
            }
        }
    }

    protected function updateVariantImages(Product $product, Request $request, array $variantDbIds)
    {
        $variantsImagesCurrentPaths = ProductVariant::where('product_id', $product->id)
                                    ->whereNotNull('image_path')
                                    ->pluck('image_path', 'id')
                                    ->toArray();
        $incomingVariantImageMap = [];
        if ($request->hasFile('variants_image')) {
            foreach ($request->file('variants_image') as $index => $variantImageArray) {
                $imageFile = $variantImageArray['img'] ?? null;
                $variantIdFromRequest = $variantImageArray['variant_id'] ?? null;
                $associatedVariantId = $variantDbIds[$variantIdFromRequest] ?? null;
                if ($imageFile && $imageFile->isValid() && $associatedVariantId) {
                    $variant = ProductVariant::find($associatedVariantId);
                    if ($variant) {
                        $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $imageFile->getClientOriginalExtension();
                        Storage::disk('public_uploads')->put($imageName, file_get_contents($imageFile));

                        if ($variant->image_path && Storage::disk('public_uploads')->exists($variant->image_path)) {
                            Storage::disk('public_uploads')->delete($variant->image_path);
                        }

                        $variant->image_path = $imageName;
                        $variant->save();
                        $incomingVariantImageMap[$associatedVariantId] = $imageName;
                    }
                }
            }
        }

        $product->variants->each(function($variant) use ($incomingVariantImageMap) {
            if ($variant->image_path && !array_key_exists($variant->id, $incomingVariantImageMap)) {
                if (Storage::disk('public_uploads')->exists($variant->image_path)) {
                    Storage::disk('public_uploads')->delete($variant->image_path);
                }
                $variant->image_path = null;
                $variant->save();
            }
        });
    }

    protected function updateProductWholesales(Product $product, Request $request)
    {
        $currentWholesaleIds = $product->wholesales->pluck('id')->toArray();
        $incomingWholesaleIds = [];
        if ($request->has('wholesales') && is_array($request->input('wholesales'))) {
            $wholesalesData = $request->input('wholesales');
            foreach ($wholesalesData as $wholesale) {
                $wholesaleId = $wholesale['id'] ?? null;
                if (is_array($wholesale)) {
                    if ($wholesaleId && in_array($wholesaleId, $currentWholesaleIds)) {
                        $wholesaleRecord = ProductWholesale::find($wholesaleId);
                        if ($wholesaleRecord) {
                            $wholesaleRecord->update([
                                'amount' => $wholesale['amount'],
                                'discount' => $wholesale['discount'],
                            ]);
                            $incomingWholesaleIds[] = $wholesaleId;
                        }
                    } else {
                        $newWholesale = new ProductWholesale([
                            'amount' => $wholesale['amount'],
                            'discount' => $wholesale['discount'],
                        ]);
                        $product->wholesales()->save($newWholesale);
                        $incomingWholesaleIds[] = $newWholesale->id;
                    }
                } else {
                    Log::warning('Unexpected type for wholesale data element: ' . gettype($wholesale));
                }
            }
        }

        $wholesalesToDelete = array_diff($currentWholesaleIds, $incomingWholesaleIds);
        if (!empty($wholesalesToDelete)) {
            ProductWholesale::destroy($wholesalesToDelete);
        }
    }

    public function update(Request $request, string $id)
    {
        $product = $this->findObject(Product::class, $id);
        $validationResponse = $this->validateProductRequest($request, $product->id);
        if ($validationResponse) {
            return $validationResponse;
        }

        DB::beginTransaction();
        $productData = $this->prepareProductData($request);
        $product->update($productData);
        $this->syncProductRelations($product, $request);
        $variantDbIds = $this->updateProductVariants($product, $request);
        $this->updateProductImages($product, $request);
        $this->updateVariantImages($product, $request, $variantDbIds);
        $this->updateProductWholesales($product, $request);
        $this->createOrUpdateProductCustomization($product, $request);
        DB::commit();
        $product->load([
            'type', 'status', 'stockStatus', 'categories',
            'variants', 'tag', 'images', 'costs',
            'attributes', 'wholesales', 'customization', 'relatedProducts',
        ]);
        $this->logAudit(Auth::user(), 'Product Updated', $request->all(), $product);
        return $this->success($product, 'Producto actualizado exitosamente', 200); 
    }

    public function delete($id)
    {
        $product = $this->findObject(Product::class, $id);
        $product->delete();
        $this->logAudit(Auth::user(), 'Delete Product', $id, $product);
        return $this->success($product, 'Product eliminado');
    }
}
