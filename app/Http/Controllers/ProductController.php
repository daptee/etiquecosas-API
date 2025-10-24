<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
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
use Str;

class ProductController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        //status_id defaul 2
        $query = Product::query()
            ->select([
                'id',
                'name',
                'sku',
                'slug',
                'price',
                'discounted_price',
                'discounted_start',
                'discounted_end',
                'stock_quantity',
                'tag_id',
                'meta_data',
                'is_feature',
                'is_customizable',
                'is_sale',
                'is_wholesale',
                'product_type_id',
                'product_status_id',
                'product_stock_status_id',
            ])
            ->with([
                'type:id,name',
                'status:id,name',
                'stockStatus:id,name',
                'categories:id,name',
                'attributes:id,name',
                'attributeValues:id,value,attribute_id',
                'tag:id,name',
                'images:id,product_id,img,is_main',
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

        if ($request->has('is_feature')) {
            $query->where('is_feature', $request->query('is_feature'));
        }
        if ($request->has('is_customizable')) {
            $query->where('is_customizable', $request->query('is_customizable'));
        }
        if ($request->has('is_sale')) {
            $query->where('is_sale', $request->query('is_sale'));
        }
        if ($request->has('is_wholesale')) {
            $query->where('is_wholesale', $request->query('is_wholesale'));
        }
        if ($request->has('has_seo')) {
            $hasSeo = $request->query('has_seo');
            if ($hasSeo) {
                $query->whereNotNull('meta_data')->where('meta_data', '!=', '[]');
            } else {
                $query->whereNull('meta_data')->orWhere('meta_data', '[]');
            }
        }

        $query->orderBy('name', 'asc');
        if (!$perPage) {
            $products = $query->get();
            $this->logAudit(Auth::user(), 'Get Product List', $request->all(), $products->first());
            return $this->success($products, 'Productos obtenidos');
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Product List', $request->all(), $products->first());
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

    public function bestSellers(Request $request)
    {
        $perPage = $request->query('quantity', 20);
        $page = $request->query('page', 1);
        $query = Product::query()
            ->select([
                'id',
                'name',
                'sku',
                'slug',
                'price',
                'discounted_price',
                'discounted_start',
                'discounted_end',
                'tag_id',
                'meta_data',
                'is_feature',
                'is_customizable',
                'is_sale',
                'is_wholesale',
                'product_type_id',
                'product_status_id',
                'product_stock_status_id',
            ])
            ->with([
                'type:id,name',
                'status:id,name',
                'stockStatus:id,name',
                'categories:id,name',
                'attributes:id,name',
                'attributeValues:id,value,attribute_id',
                'tag:id,name',
                'images:id,product_id,img,is_main',
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

        // cambiado el orden para los productos más vendidos
        $query->orderBy('name', 'asc');
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
        // Si no se encuentra el producto, retornar error 404
        if (!$product) {
            return $this->error('Producto no encontrado', 404);
        }
        $product->load([
            'type:id,name',
            'status:id,name',
            'stockStatus:id,name',
            'categories:id,name',
            'attributes:id,name',
            'attributeValues:id,value,attribute_id',
            'tag:id,name',
            'images:id,product_id,img,is_main',
            'costs:id,name,price',
            'customization',
            'wholesales:id,product_id,amount,discount',
            'relatedProducts:id,name,sku,slug,price,discounted_price,discounted_start,discounted_end,product_type_id,product_status_id,product_stock_status_id',
            'relatedProducts.images:id,product_id,img,is_main',
            'variants'
        ])->makeHidden([
                    'created_at',
                    'updated_at'
                ]);
        return $this->success($product, 'Producto obtenido exitosamente');
    }

    public function slug(Request $request, string $slug)
    {
        $product = Product::where('slug', $slug)->first();

        // Si no se encuentra el producto, retornar error 404
        if (!$product) {
            return $this->error('Producto no encontrado', 404);
        }
        $product->load([
            'type:id,name',
            'status:id,name',
            'stockStatus:id,name',
            'categories:id,name',
            'attributes:id,name',
            'attributeValues:id,value,attribute_id',
            'tag:id,name',
            'images:id,product_id,img,is_main',
            'costs:id,name',
            'customization',
            'wholesales:id,product_id,amount,discount',
            'relatedProducts:id,name,sku,slug,price,discounted_price,discounted_start,discounted_end,product_type_id,product_status_id,product_stock_status_id',
            'relatedProducts.images:id,product_id,img,is_main',
            'variants'
        ])->makeHidden([
                    'created_at',
                    'updated_at'
                ]);
        $this->logAudit(Auth::user(), 'Get Product Details', ['product_id' => $product->id], $product);
        return $this->success($product, 'Producto obtenido exitosamente');
    }

    protected function validateProductRequest(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')],
            'product_type_id' => 'required|integer|exists:product_types,id',
            'price' => 'required|numeric',
            'discounted_price' => 'nullable|numeric|lt:price',
            'discounted_start' => 'nullable|date',
            'discounted_end' => 'nullable|date',
            'product_stock_status_id' => 'required|integer|exists:product_stock_statuses,id',
            'stock_quantity' => 'nullable|integer',
            'wholesale_price' => 'nullable|numeric',
            'wholesale_min_amount' => 'nullable|integer|min:0',
            'tag_id' => 'nullable|exists:configuration_tags,id',
            'costs' => 'nullable|array',
            'costs.*' => 'exists:costs,id',
            'categories' => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
            'wholesales' => 'nullable|array',
            'wholesales.*.amount' => 'required_with:wholesales|numeric|min:0',
            'wholesales.*.discount' => 'required_with:wholesales|numeric|min:0',
            'description' => 'nullable|string',
            'shortDescription' => 'nullable|string',
            'shipping_text' => 'nullable|string',
            'shipping_time_text' => 'nullable|string',
            'notifications_text' => 'nullable|string',
            'tutorial_link' => 'nullable|url|max:2048',
            'is_customizable' => 'nullable|boolean',
            'is_feature' => 'nullable|boolean',
            'is_sale' => 'nullable|boolean',
            'is_wholesale' => 'nullable|boolean',
            'product_status_id' => 'required|integer|exists:product_statuses,id',
            'related_products' => 'nullable|array',
            'related_products.*' => 'exists:products,id',
            'attributes' => 'nullable|array',
            'attributes.*' => 'exists:attributes,id',
            'attributes_values' => 'nullable|array',
            'attributes_values.*' => 'exists:attribute_values,id',
            'variants' => 'nullable|array',
            'variants.*.attributesvalues' => 'nullable|array',
            'variants.*.attributesvalues.*.id' => 'nullable|numeric',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => 'nullable|numeric',
            'variants.*.discounted_price' => 'nullable|numeric',
            'variants.*.discounted_start' => 'nullable|date',
            'variants.*.discounted_end' => 'nullable|date',
            'variants.*.stock_status' => 'nullable|integer|exists:product_stock_statuses,id',
            'variants.*.stock_quantity' => 'nullable|integer',
            'variants.*.wholesale_price' => 'nullable|numeric',
            'variants.*.wholesale_min_amount' => 'nullable|integer|min:0',
            'variants.*.img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'meta_data' => 'nullable|json',
            'customization' => 'nullable|json',
            'images' => 'nullable|array',
            'images.*.img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'main_image_index' => 'nullable|integer|min:0',
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
            'customization.is_name_active' => 'nullable|integer|in:0,1',
            'customization.is_last_name_active' => 'nullable|integer|in:0,1',
            'customization.is_text_active' => 'nullable|integer|in:0,1',
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
            'costs',
            'attributes',
            'customization',
            'wholesales',
            'related_products',
        ]);

        $productData['is_feature'] = (bool) ($request->input('is_feature', false));
        $productData['is_customizable'] = (bool) ($request->input('is_customizable', false));
        $productData['is_sale'] = (bool) ($request->input('is_sale', true));
        $productData['is_wholesale'] = (bool) ($request->input('is_wholesale', false));

        // Decodificar JSON de campos como meta_data y customization
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
            $categories = collect($request->categories)
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->categories()->sync($categories);
        } else {
            $product->categories()->detach();
        }

        if ($request->has('costs')) {
            $costs = collect($request->costs)
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->costs()->sync($costs);
        } else {
            $product->costs()->detach();
        }

        if ($request->has('related_products')) {
            $relatedProducts = collect($request->related_products)
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->relatedProducts()->sync($relatedProducts);
        } else {
            $product->relatedProducts()->detach();
        }

        if ($request->has('attributes')) {
            $attributes = $request->input('attributes');

            // Si es un string JSON (ej: '["6"]'), decodificarlo
            if (is_string($attributes)) {
                $attributes = json_decode($attributes, true);
            }

            Log::info("Syncing attributes for product ID {$product->id}: ", $attributes);

            $product->attributes()->sync($attributes);
        } else {
            $product->attributes()->detach();
        }

        if ($request->has('attributes_values')) {
            $attributesValues = collect($request->input('attributes_values'))
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->attributeValues()->sync($attributesValues);
        } else {
            $product->attributeValues()->detach();
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
                        'is_main' => ((int) $request->input('main_image_index') === $index) ? 1 : 0,
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
                    Log::error("createProductVariants: Variant data at index $index is not an array: " . json_encode($variantData));
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "variants.$index" => ['Los datos de la variante no son válidos.'],
                    ]);
                }

                // 1️⃣ Validación
                $singleVariantValidator = Validator::make($variantData, [
                    'attributes' => 'nullable|array',
                    'attributes.*.attribute_id' => 'required|integer|exists:attributes,id',
                    'attributesvalues' => 'nullable|array',
                    'attributesvalues.*.id' => 'nullable|numeric|exists:attribute_values,id',
                    'attributesvalues.*.attribute_id' => 'nullable|integer|exists:attributes,id',
                    'name' => 'nullable|string|max:255',
                    'sku' => ['nullable', 'string', 'max:255'],
                    'price' => 'nullable|numeric',
                    'discounted_price' => 'nullable|numeric|lt:price',
                    'discounted_start' => 'nullable|date',
                    'discounted_end' => 'nullable|date',
                    'stock_status' => 'nullable|integer|exists:product_stock_statuses,id',
                    'stock_quantity' => 'nullable|integer',
                    'wholesale_price' => 'nullable|numeric',
                    'wholesale_min_amount' => 'nullable|integer|min:0',
                    'img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                ]);
                if ($singleVariantValidator->fails()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "variants.$index" => $singleVariantValidator->errors()->all(),
                    ]);
                }

                // 2️⃣ Convertimos en arrays por atributo
                // 🔹 Recolectar grupos de valores para combinaciones
                $attrGroups = [];

                // 1️⃣ Attributes → traer TODOS los values y armar grupo
                if (!empty($variantData['attributes'])) {
                    foreach ($variantData['attributes'] as $attr) {
                        if (isset($attr['attribute_id']) && $attr['attribute_id']) {
                            $allValues = AttributeValue::where('attribute_id', $attr['attribute_id'])
                                ->pluck('id')
                                ->toArray();
                            if (!empty($allValues)) {
                                $attrGroups[] = $allValues;
                            }
                        }
                    }
                }

                // 2️⃣ Guardar los attribute values enviados para agregarlos a TODAS las combinaciones
                $fixedValues = [];
                if (!empty($variantData['attributesvalues'])) {
                    foreach ($variantData['attributesvalues'] as $val) {
                        if (isset($val['id']) && $val['id']) {
                            $fixedValues[] = [
                                'id' => $val['id']
                            ];
                        }
                    }
                }

                // 3️⃣ Generar todas las combinaciones posibles SOLO de los atributos
                $combinations = [[]];
                foreach ($attrGroups as $group) {
                    $tmp = [];
                    foreach ($combinations as $comb) {
                        foreach ($group as $valueId) {
                            $tmp[] = array_merge($comb, [$valueId]);
                        }
                    }
                    $combinations = $tmp;
                }

                // 4️⃣ Crear variantes para cada combinación + agregar los valores fijos
                foreach ($combinations as $combination) {
                    $variantDataCopy = $variantData;
                    $variantDataCopy['attributesvalues'] = [];

                    // Agregar los values generados por el atributo
                    foreach ($combination as $valueId) {
                        $attrVal = AttributeValue::find($valueId);
                        if ($attrVal) {
                            $variantDataCopy['attributesvalues'][] = [
                                'id' => $attrVal->id
                            ];
                        }
                    }

                    // Agregar los values fijos enviados en attributesvalues
                    foreach ($fixedValues as $fixed) {
                        $variantDataCopy['attributesvalues'][] = $fixed;
                    }

                    unset($variantDataCopy['attributes']); // No se guarda directamente

                    // Guardar imagen
                    $imagePath = null;
                    $variantImageFile = $request->file("variants.$index.img");
                    if ($variantImageFile instanceof \Illuminate\Http\UploadedFile && $variantImageFile->isValid()) {
                        $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $variantImageFile->getClientOriginalExtension();
                        Storage::disk('public_uploads')->put($imageName, file_get_contents($variantImageFile->getRealPath()));
                        $imagePath = $imageName;
                    }

                    $newVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'variant' => $variantDataCopy,
                        'sku' => $variantData['sku'] ?? null,
                        'name' => $variantData['name'] ?? null,
                        'price' => $variantData['price'] ?? null,
                        'discounted_price' => $variantData['discounted_price'] ?? null,
                        'discounted_start' => $variantData['discounted_start'] ?? null,
                        'discounted_end' => $variantData['discounted_end'] ?? null,
                        'stock_status' => $variantData['stock_status'] ?? null,
                        'stock_quantity' => $variantData['stock_quantity'] ?? null,
                        'wholesale_price' => $variantData['wholesale_price'] ?? null,
                        'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                        'img' => $imagePath ?? null,
                    ]);

                    $variantDbIds[] = $newVariant->id;
                }
            }
        }

        return $variantDbIds;
    }

    /**
     * Genera el producto cartesiano de un array de arrays
     */
    private function cartesianProduct(array $arrays): array
    {
        $result = [[]];
        foreach ($arrays as $propertyValues) {
            $tmp = [];
            foreach ($result as $resultItem) {
                foreach ($propertyValues as $propertyValue) {
                    $tmp[] = array_merge($resultItem, [$propertyValue]);
                }
            }
            $result = $tmp;
        }
        return $result;
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
        // Generamos SKU si no viene enviado
        if (!$request->filled('sku')) {
            $words = explode(' ', $request->input('name'));
            $initials = '';
            foreach ($words as $word) {
                $initials .= strtoupper(mb_substr($word, 0, 1));
            }
            $productData['sku'] = $initials . '-' . ($product->id ?? uniqid());
            $product->sku = $productData['sku'];
            $product->save();
        } else {
            $productData['sku'] = $request->input('sku');
            $product->sku = $productData['sku'];
            $product->save();
        }
        // Generar slug si no viene enviado
        if (!$request->filled('slug')) {
            $product->slug = Str::slug($product->name) . '-' . $product->id;
            $product->save();
        }
        $this->syncProductRelations($product, $request);
        $variantDbIds = $this->createProductVariants($product, $request);
        $this->createProductWholesales($product, $request);
        $this->createOrUpdateProductCustomization($product, $request);
        $this->createProductImages($product, $request);
        DB::commit();
        $this->logAudit(Auth::user(), 'Product Created', $request->all(), $product);
        $product->load([
            'type',
            'status',
            'stockStatus',
            'categories',
            'attributes',
            'attributeValues',
            'variants',
            'tag',
            'images',
            'costs',
            'customization',
            'wholesales',
            'relatedProducts',
        ]);
        return $this->success($product, 'Producto creado exitosamente', 201);
    }

    protected function validateProductUpdateRequest(Request $request, int $productId)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($productId)],
            'product_type_id' => 'required|integer|exists:product_types,id',
            'price' => 'required|numeric',
            'discounted_price' => 'nullable|numeric|lt:price',
            'discounted_start' => 'nullable|date',
            'discounted_end' => 'nullable|date',
            'product_stock_status_id' => 'required|integer|exists:product_stock_statuses,id',
            'stock_quantity' => 'nullable|integer',
            'wholesale_price' => 'nullable|numeric',
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
            'shortDescription' => 'nullable|string',
            'shipping_text' => 'nullable|string',
            'shipping_time_text' => 'nullable|string',
            'notifications_text' => 'nullable|string',
            'tutorial_link' => 'nullable|url|max:2048',
            'is_customizable' => 'nullable|boolean',
            'is_feature' => 'nullable|boolean',
            'is_sale' => 'nullable|boolean',
            'is_wholesale' => 'nullable|boolean',
            'product_status_id' => 'required|integer|exists:product_statuses,id',
            'related_products' => 'nullable|array',
            'related_products.*' => 'integer|exists:products,id',
            'attributes' => 'nullable|array',
            'attributes.*' => 'integer|exists:attributes,id',
            'attributes_values' => 'nullable|array',
            'attributes_values.*' => 'exists:attribute_values,id',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer|exists:product_variants,id',
            'variants.*.attributesvalues' => 'nullable|array',
            'variants.*.attributesvalues.*.attribute_id' => 'nullable|integer|exists:attributes,id',
            'variants.*.attributesvalues.*.id' => 'nullable|numeric',
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.price' => 'nullable|numeric',
            'variants.*.discounted_price' => 'nullable|numeric',
            'variants.*.discounted_start' => 'nullable|date',
            'variants.*.discounted_end' => 'nullable|date',
            'variants.*.stock_status' => 'nullable|integer|exists:product_stock_statuses,id',
            'variants.*.stock_quantity' => 'nullable|integer',
            'variants.*.wholesale_price' => 'nullable|numeric',
            'variants.*.wholesale_min_amount' => 'nullable|integer|min:0',
            'variants.*.img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'variants.*.delete_img' => 'nullable|boolean',
            'meta_data' => 'nullable|json',
            'customization' => 'nullable|json',
            'images' => 'nullable|array',
            'images.*.id' => 'nullable|integer|exists:product_images,id',
            'images.*.img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'main_image_index' => 'nullable|integer|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Product Update Validation Fail (Initial)', $request->all(), $validator->errors());
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
                    $this->logAudit(Auth::user(), 'Product Update Validation Fail (JSON Decode Error)', $request->all(), $validator->errors());
                    return $this->validationError($validator->errors());
                }
            }
        }
        $internalJsonRules = [
            'customization' => 'nullable|array',
            'customization.is_details_active' => 'nullable|integer|in:0,1',
            'customization.is_colors_active' => 'nullable|integer|in:0,1',
            'customization.is_icons_active' => 'nullable|integer|in:0,1',
            'customization.is_name_active' => 'nullable|integer|in:0,1',
            'customization.is_last_name_active' => 'nullable|integer|in:0,1',
            'customization.is_text_active' => 'nullable|integer|in:0,1',
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
            $this->logAudit(Auth::user(), 'Product Update Validation Fail (Internal JSON)', $decodedData, $internalValidator->errors());
            return $this->validationError($internalValidator->errors());
        }

        return null;
    }

    protected function prepareProductUpdateData(Request $request, Product $product): array
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
        $productData['is_feature'] = (bool) ($request->input('is_feature', false));
        $productData['is_customizable'] = (bool) ($request->input('is_customizable', false));
        $productData['is_sale'] = (bool) ($request->input('is_sale', true));
        $productData['is_wholesale'] = (bool) ($request->input('is_wholesale', false));

        if ($request->filled('name') && $request->input('name') !== $product->name) {
            $productData['slug'] = $this->generateUniqueSlug($request->input('name'), $product->id);
        }

        // solo actualizar SKU si viene en request o si no existe
        if ($request->filled('sku')) {
            $productData['sku'] = $request->input('sku');
        } elseif (empty($product->sku)) {
            $words = explode(' ', $request->input('name'));
            $initials = '';
            foreach ($words as $word) {
                $initials .= strtoupper(mb_substr($word, 0, 1));
            }
            $productData['sku'] = $initials . '-' . ($product->id ?? uniqid());
        }

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

        if (!$request->filled('discounted_start')) {
            $productData['discounted_start'] = null;
        }
        if (!$request->filled('discounted_end')) {
            $productData['discounted_end'] = null;
        }

        return $productData;
    }

    protected function syncProductUpdateRelations(Product $product, Request $request)
    {
        if ($request->has('categories')) {
            $categories = collect($request->categories)
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->categories()->sync($categories);
        } else {
            $product->categories()->detach();
        }

        if ($request->has('costs')) {
            $costs = collect($request->costs)
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->costs()->sync($costs);
        } else {
            $product->costs()->detach();
        }

        if ($request->has('attributes')) {
            $attributes = $request->input('attributes');

            // Si es un string JSON (ej: '["6"]'), decodificarlo
            if (is_string($attributes)) {
                $attributes = json_decode($attributes, true);
            }

            Log::info("Syncing attributes for product ID {$product->id}: ", $attributes);

            $product->attributes()->sync($attributes);

        } else {
            $product->attributes()->detach();
        }

        if ($request->has('related_products')) {
            $relatedProducts = collect($request->related_products)
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->relatedProducts()->sync($relatedProducts);
        } else {
            $product->relatedProducts()->detach();
        }

        if ($request->has('attributes_values')) {
            $attributesValues = collect($request->input('attributes_values'))
                ->filter(fn($id) => is_numeric($id))
                ->map(fn($id) => (int) $id)
                ->toArray();
            $product->attributeValues()->sync($attributesValues);
        } else {
            $product->attributeValues()->detach();
        }
    }

    protected function updateProductVariants(Product $product, Request $request): array
    {
        $variantDbIds = [];

        // si no existen variantes eliminamos las existentes
        if (!$request->has('variants') || !is_array($request->input('variants'))) {
            $product->variants()->delete();
            return $variantDbIds;
        }

        if ($request->has('variants') && is_array($request->input('variants'))) {
            $variantsArray = $request->input('variants');

            foreach ($variantsArray as $index => $variantData) {
                if (!is_array($variantData)) {
                    Log::error("updateProductVariants: Variant data at index $index is not an array: " . json_encode($variantData));
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "variants.$index" => ['Los datos de la variante no son válidos (cada variante debe ser un objeto).'],
                    ]);
                }

                // 🔹 Validación básica
                $singleVariantValidator = Validator::make($variantData, [
                    'id' => 'nullable|exists:product_variants,id',
                    'attributes' => 'nullable|array',
                    'attributes.*.attribute_id' => 'required|integer|exists:attributes,id',
                    'attributesvalues' => 'nullable|array',
                    'attributesvalues.*.id' => 'nullable|numeric',
                    'attributesvalues.*.attribute_id' => 'nullable|integer|exists:attributes,id',
                    'sku' => ['nullable', 'string', 'max:255'],
                    'name' => 'nullable|string|max:255',
                    'price' => 'nullable|numeric',
                    'discounted_price' => 'nullable|numeric|lt:price',
                    'discounted_start' => 'nullable|date',
                    'discounted_end' => 'nullable|date',
                    'stock_status' => 'nullable|integer|exists:product_stock_statuses,id',
                    'stock_quantity' => 'nullable|integer',
                    'wholesale_price' => 'nullable|numeric',
                    'wholesale_min_amount' => 'nullable|integer|min:0',
                    'img' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                ]);

                if ($singleVariantValidator->fails()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "variants.$index" => $singleVariantValidator->errors()->all(),
                    ]);
                }

                // 🔹 Recolectar grupos para combinaciones
                $attrGroups = [];
                if (!empty($variantData['attributes'])) {
                    foreach ($variantData['attributes'] as $attr) {
                        if (isset($attr['attribute_id']) && $attr['attribute_id']) {
                            $allValues = AttributeValue::where('attribute_id', $attr['attribute_id'])
                                ->pluck('id')
                                ->toArray();
                            if (!empty($allValues)) {
                                $attrGroups[] = $allValues;
                            }
                        }
                    }
                }

                // 🔹 Guardar values fijos para todas las combinaciones
                $fixedValues = [];
                if (!empty($variantData['attributesvalues'])) {
                    foreach ($variantData['attributesvalues'] as $val) {
                        if (isset($val['id']) && $val['id']) {
                            $fixedValues[] = [
                                'id' => $val['id']
                            ];
                        }
                    }
                }

                // 🔹 Generar combinaciones
                $combinations = [[]];
                foreach ($attrGroups as $group) {
                    $tmp = [];
                    foreach ($combinations as $comb) {
                        foreach ($group as $valueId) {
                            $tmp[] = array_merge($comb, [$valueId]);
                        }
                    }
                    $combinations = $tmp;
                }

                // 🔹 Procesar cada combinación como una variante
                foreach ($combinations as $combination) {
                    $variantDataCopy = $variantData;
                    $variantDataCopy['attributesvalues'] = [];

                    // Agregar values generados por atributos
                    foreach ($combination as $valueId) {
                        $attrVal = AttributeValue::find($valueId);
                        if ($attrVal) {
                            $variantDataCopy['attributesvalues'][] = [
                                'id' => $attrVal->id
                            ];
                        }
                    }

                    // Agregar values fijos
                    foreach ($fixedValues as $fixed) {
                        $variantDataCopy['attributesvalues'][] = $fixed;
                    }

                    // Eliminar attributes
                    unset($variantDataCopy['attributes']);

                    $variantImageFile = $request->file("variants.$index.img");
                    $imagePath = null;
                    $variant = null;

                    if (!empty($variantData['id'])) {
                        // 🔹 Update
                        $variant = ProductVariant::find($variantData['id']);

                        if (!empty($variantData['delete_img']) && $variantData['delete_img']) {
    // 🔹 Solo borrar si se pidió explícitamente eliminar
    if ($variant->img && Storage::disk('public_uploads')->exists($variant->img)) {
        Storage::disk('public_uploads')->delete($variant->img);
    }
    $imagePath = null;
} elseif ($variantImageFile instanceof \Illuminate\Http\UploadedFile && $variantImageFile->isValid()) {
    // 🔹 Solo reemplazar si se sube una imagen nueva
    if ($variant->img && Storage::disk('public_uploads')->exists($variant->img)) {
        Storage::disk('public_uploads')->delete($variant->img);
    }
    $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $variantImageFile->getClientOriginalExtension();
    Storage::disk('public_uploads')->put($imageName, file_get_contents($variantImageFile->getRealPath()));
    $imagePath = $imageName;
} else {
    // 🔹 Mantener la imagen actual
    $imagePath = $variant->img;
}

                        $variant->update([
                            'variant' => $variantDataCopy,
                            'sku' => $variantData['sku'] ?? null,
                            'name' => $variantData['name'] ?? null,
                            'price' => $variantData['price'] ?? null,
                            'discounted_price' => $variantData['discounted_price'] ?? null,
                            'discounted_start' => $variantData['discounted_start'] ?? null,
                            'discounted_end' => $variantData['discounted_end'] ?? null,
                            'stock_status' => $variantData['stock_status'] ?? null,
                            'stock_quantity' => $variantData['stock_quantity'] ?? null,
                            'wholesale_price' => $variantData['wholesale_price'] ?? null,
                            'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                            'img' => $imagePath,
                        ]);
                    } else {
                        // 🔹 Create
                        if ($variantImageFile instanceof \Illuminate\Http\UploadedFile && $variantImageFile->isValid()) {
                            $imageName = 'images/product_variants/' . uniqid('img_') . '.' . $variantImageFile->getClientOriginalExtension();
                            Storage::disk('public_uploads')->put($imageName, file_get_contents($variantImageFile->getRealPath()));
                            $imagePath = $imageName;
                        }

                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'variant' => $variantDataCopy,
                            'sku' => $variantData['sku'] ?? null,
                            'name' => $variantData['name'] ?? null,
                            'price' => $variantData['price'] ?? null,
                            'discounted_price' => $variantData['discounted_price'] ?? null,
                            'discounted_start' => $variantData['discounted_start'] ?? null,
                            'discounted_end' => $variantData['discounted_end'] ?? null,
                            'stock_status' => $variantData['stock_status'] ?? null,
                            'stock_quantity' => $variantData['stock_quantity'] ?? null,
                            'wholesale_price' => $variantData['wholesale_price'] ?? null,
                            'wholesale_min_amount' => $variantData['wholesale_min_amount'] ?? null,
                            'img' => $imagePath,
                        ]);
                    }

                    $variantDbIds[] = $variant->id;
                }
            }

            // 🔹 Eliminar las variantes que no llegaron en el request
            $product->variants()
                ->whereNotIn('id', $variantDbIds)
                ->get()
                ->each(function ($variant) {
                    if ($variant->img && Storage::disk('public_uploads')->exists($variant->img)) {
                        Storage::disk('public_uploads')->delete($variant->img);
                    }
                    $variant->delete();
                });
        }

        return $variantDbIds;
    }


    protected function updateProductImages(Product $product, Request $request)
    {
        $currentImageIds = $product->images->pluck('id')->toArray();
        $incomingImageIds = [];
        $mainImageIndex = (int) $request->input('main_image_index', 0);
        $mainImageCandidateId = null;

        // Usamos $request->all() para traer TODO (inputs + archivos)
        $allIncomingImagesData = $request->all()['images'] ?? null;
        $imageFiles = $request->file('images', []);
        $imagesCollection = collect($imageFiles);

        if (!is_array($allIncomingImagesData)) {
            Log::info("No se enviaron datos válidos en 'images' del request");
            // Opcional: podés decidir qué hacer si no hay imágenes válidas
            return;
        }

        foreach ($allIncomingImagesData as $index => $imageData) {
            $imageId = $imageData['id'] ?? null;
            $file = $imagesCollection->get($index)['img'] ?? null;

            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $filename = 'images/products/' . uniqid('img_') . '.' . $file->getClientOriginalExtension();
                Storage::disk('public_uploads')->put($filename, file_get_contents($file->getRealPath()));

                if ($imageId && in_array($imageId, $currentImageIds)) {
                    // Reemplazar imagen existente
                    $image = ProductImage::find($imageId);
                    if ($image) {
                        if ($image->img && Storage::disk('public_uploads')->exists($image->img)) {
                            Storage::disk('public_uploads')->delete($image->img);
                        }
                        $image->update(['img' => $filename]);
                        $incomingImageIds[] = $imageId;
                    }
                } else {
                    // Crear nueva imagen
                    $newImage = ProductImage::create([
                        'product_id' => $product->id,
                        'img' => $filename,
                        'is_main' => 0,
                    ]);
                    $incomingImageIds[] = $newImage->id;
                    $imageId = $newImage->id; // para marcar como principal
                }
            } elseif ($imageId && in_array($imageId, $currentImageIds)) {
                // No hay archivo nuevo, conservar la imagen existente
                $incomingImageIds[] = $imageId;
            }

            // Marcar la imagen principal según el índice
            if ($mainImageIndex === $index && $imageId) {
                $mainImageCandidateId = $imageId;
            }
        }

        // Eliminar imágenes que no fueron enviadas en la request
        $imagesToDelete = array_diff($currentImageIds, $incomingImageIds);
        foreach ($imagesToDelete as $id) {
            $image = ProductImage::find($id);
            if ($image) {
                if ($image->img && Storage::disk('public_uploads')->exists($image->img)) {
                    Storage::disk('public_uploads')->delete($image->img);
                }
                $image->delete();
            }
        }

        // Actualizar la imagen principal
        if ($mainImageCandidateId) {
            ProductImage::where('product_id', $product->id)->update(['is_main' => 0]);
            ProductImage::where('id', $mainImageCandidateId)->update(['is_main' => 1]);
        }

        Log::info('Final image IDs kept:', $incomingImageIds);
        Log::info('Deleted image IDs:', $imagesToDelete);
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
        $validationResponse = $this->validateProductUpdateRequest($request, $product->id);
        if ($validationResponse) {
            return $validationResponse;
        }

        DB::beginTransaction();
        $productData = $this->prepareProductUpdateData($request, $product);
        $product->update($productData);
        $this->syncProductUpdateRelations($product, $request);
        $variantDbIds = $this->updateProductVariants($product, $request);
        $this->updateProductImages($product, $request);
        $this->updateProductWholesales($product, $request);
        $this->createOrUpdateProductCustomization($product, $request);
        DB::commit();
        $this->logAudit(Auth::user(), 'Product Updated', $request->all(), $product);
        $product->load([
            'type',
            'status',
            'stockStatus',
            'categories',
            'attributes',
            'attributeValues',
            'variants',
            'tag',
            'images',
            'costs',
            'wholesales',
            'customization',
            'relatedProducts',
        ]);
        return $this->success($product, 'Producto actualizado exitosamente', 200);
    }

    public function bulkAssignCategories(Request $request)
    {
        $rules = [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
            'mode' => 'nullable|string|in:sync,attach,detach', // Opcional: para definir el comportamiento
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $productIds = $request->input('product_ids');
        $categoryIds = $request->input('category_ids');
        $mode = $request->input('mode', 'sync');

        DB::beginTransaction();

        try {
            $updatedProducts = [];

            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                if (!$product)
                    continue;

                switch ($mode) {
                    case 'attach':
                        $product->categories()->syncWithoutDetaching($categoryIds);
                        break;

                    case 'detach':
                        $product->categories()->detach($categoryIds);
                        break;

                    case 'sync':
                    default:
                        $product->categories()->sync($categoryIds);
                        break;
                }

                // 🔹 Recargar el producto con sus categorías actualizadas
                $product->load('categories:id,name');
                $updatedProducts[] = $product;
            }

            DB::commit();

            $this->logAudit(Auth::user(), 'Bulk Assign Categories', $request->all(), [
                'product_ids' => $productIds,
                'category_ids' => $categoryIds,
                'mode' => $mode,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categorías asignadas exitosamente a los productos seleccionados.',
                'data' => $updatedProducts,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en bulkAssignCategories: ' . $e->getMessage(), [
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al asignar las categorías.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function delete($id)
    {
        $product = $this->findObject(Product::class, $id);
        $product->delete();
        $this->logAudit(Auth::user(), 'Delete Product', $id, $product);
        return $this->success($product, 'Product eliminado');
    }

    public function generateUniqueSlug(string $name, $productId = null): string
    {
        // Convertimos el nombre a slug básico
        $slug = Str::slug($name);
        $slug = $slug . '-' . $productId;

        // Guardamos el slug original para usarlo en caso de duplicados
        $originalSlug = $slug;
        $counter = 1;

        // Validamos si ya existe un slug igual
        while (
            Product::where('slug', $slug)
                ->when($productId, fn($query) => $query->where('id', '!=', $productId))
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter++;
        }

        Log::info("Generated unique slug: $slug for product name: $name");

        return $slug;
    }

    protected function generateUniqueSku(string $name, int $productId = null): string
    {
        $baseSku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8));
        $sku = $baseSku . '-' . $productId;

        while (Product::where('sku', $sku)->when($productId, fn($q) => $q->where('id', '!=', $productId))->exists()) {
            $sku = $baseSku . '-' . $productId;
        }

        return $sku;
    }

}
