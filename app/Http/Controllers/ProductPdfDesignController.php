<?php

namespace App\Http\Controllers;

use App\Models\ProductPdfDesign;
use App\Models\ProductPdfDesignProduct;
use App\Services\EtiquetaService;
use App\Services\PdfDesignSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ProductPdfDesignController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $productId = $request->query('productId');
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $query = ProductPdfDesign::with(['products:id,name,sku', 'labelShape', 'generalStatus']);

        if ($productId) {
            $query->whereHas('products', fn($q) => $q->where('products.id', $productId));
        }

        if ($statusId = $request->query('statusId')) {
            $query->where('status_id', $statusId);
        }

        if ($labelShapeId = $request->query('labelShapeId')) {
            $query->where('label_shape_id', $labelShapeId);
        }

        if ($request->filled('isPublished')) {
            $query->where('is_published', $request->boolean('isPublished'));
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $designs = $query->get();
            return $this->success($designs, 'Diseños de PDF obtenidos');
        }

        $designs = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $designs->currentPage(),
            'last_page' => $designs->lastPage(),
            'per_page' => $designs->perPage(),
            'total' => $designs->total(),
            'from' => $designs->firstItem(),
            'to' => $designs->lastItem(),
        ];
        return $this->success($designs->items(), 'Diseños de PDF obtenidos', $metaData);
    }

    public function show($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        return $this->success($design, 'Diseño de PDF obtenido');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId,
            'name' => $request->name,
            'data' => $this->sanitizeDesignData($request->input('data')),
            'is_published' => $request->boolean('isPublished'),
            'status_id' => $request->statusId ?? 1,
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF creado');
    }

    public function update(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $rules = $this->rules();
        $rules['name'] = 'nullable|string|max:255';
        $rules['data'] = 'nullable|array';

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $design->update([
            'label_shape_id' => $request->input('labelShapeId', $design->label_shape_id),
            'name' => $request->input('name', $design->name),
            'data' => $request->has('data') ? $this->sanitizeDesignData($request->input('data')) : $design->data,
            'is_published' => $request->has('isPublished') ? $request->boolean('isPublished') : $design->is_published,
            'status_id' => $request->input('statusId', $design->status_id),
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF actualizado');
    }

    public function toggleStatus($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->update([
            'status_id' => $design->status_id === 1 ? 2 : 1,
        ]);
        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Toggle Product Pdf Design Status', $id, $design);
        return $this->success($design, 'Estado actualizado');
    }

    public function delete($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->delete();
        $this->logAudit(Auth::user(), 'Delete Product Pdf Design', $id, $design);
        return $this->success($design, 'Diseño de PDF eliminado');
    }

    /**
     * Vincula este diseño a un producto (con la variante/temática que lo
     * selecciona en ese producto puntual). Un mismo diseño puede vincularse
     * a varios productos distintos.
     */
    public function attachProduct(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'themeKey' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Attach Product to Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        try {
            $link = ProductPdfDesignProduct::create([
                'product_pdf_design_id' => $design->id,
                'product_id' => $request->productId,
                'theme_key' => $request->themeKey,
            ]);
        } catch (QueryException $e) {
            return $this->validationError(['themeKey' => ['Ya existe un diseño vinculado a este producto y esta variante/temática']]);
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Attach Product to Pdf Design', $request->all(), $link);
        return $this->success($design, 'Producto vinculado al diseño');
    }

    /**
     * Vincula este diseño a un producto para VARIAS temáticas/variantes a la
     * vez (un theme_key por vínculo). Si alguna combinación producto+theme_key
     * ya estaba vinculada a otro diseño, esa puntual se salta (no aborta el
     * resto) y se informa en "skipped".
     */
    public function bulkAttachProducts(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'themeKeys' => 'required|array|min:1',
            'themeKeys.*' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Attach Products to Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $created = [];
        $skipped = [];

        foreach ($request->themeKeys as $themeKey) {
            try {
                $link = ProductPdfDesignProduct::create([
                    'product_pdf_design_id' => $design->id,
                    'product_id' => $request->productId,
                    'theme_key' => $themeKey,
                ]);
                $created[] = $link;
            } catch (QueryException $e) {
                $skipped[] = [
                    'themeKey' => $themeKey,
                    'reason' => 'Ya existe un diseño vinculado a este producto y esta variante/temática',
                ];
            }
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Attach Products to Pdf Design', $request->all(), [
            'created' => count($created),
            'skipped' => $skipped,
        ]);

        return $this->success($design, 'Vínculos creados', [
            'created' => count($created),
            'skipped' => $skipped,
        ]);
    }

    /**
     * Desvincula este diseño de un producto para VARIAS temáticas/variantes a
     * la vez. Para desvincular el vínculo "sin variante" (theme_key null),
     * incluí `null` dentro de themeKeys.
     */
    public function bulkDetachProducts(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'themeKeys' => 'required|array|min:1',
            'themeKeys.*' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Detach Products from Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $themeKeys = $request->themeKeys;
        $hasNull = in_array(null, $themeKeys, true);
        $nonNullKeys = array_values(array_filter($themeKeys, fn($k) => $k !== null));

        $deletedCount = ProductPdfDesignProduct::where('product_pdf_design_id', $design->id)
            ->where('product_id', $request->productId)
            ->where(function ($q) use ($nonNullKeys, $hasNull) {
                if (!empty($nonNullKeys)) {
                    $q->whereIn('theme_key', $nonNullKeys);
                }
                if ($hasNull) {
                    $q->orWhereNull('theme_key');
                }
            })
            ->delete();

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Detach Products from Pdf Design', $request->all(), ['deleted' => $deletedCount]);

        return $this->success($design, 'Vínculos eliminados', ['deleted' => $deletedCount]);
    }

    /**
     * Igual que bulkAttachProducts, pero para VARIOS productos a la vez, cada
     * uno con su propia lista de temáticas/variantes.
     */
    public function bulkAttachMany(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'links' => 'required|array|min:1',
            'links.*.productId' => 'required|exists:products,id',
            'links.*.themeKeys' => 'required|array|min:1',
            'links.*.themeKeys.*' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Attach Many Products to Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $created = [];
        $skipped = [];

        foreach ($request->links as $link) {
            foreach ($link['themeKeys'] as $themeKey) {
                try {
                    $created[] = ProductPdfDesignProduct::create([
                        'product_pdf_design_id' => $design->id,
                        'product_id' => $link['productId'],
                        'theme_key' => $themeKey,
                    ]);
                } catch (QueryException $e) {
                    $skipped[] = [
                        'productId' => $link['productId'],
                        'themeKey' => $themeKey,
                        'reason' => 'Ya existe un diseño vinculado a este producto y esta variante/temática',
                    ];
                }
            }
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Attach Many Products to Pdf Design', $request->all(), [
            'created' => count($created),
            'skipped' => $skipped,
        ]);

        return $this->success($design, 'Vínculos creados', [
            'created' => count($created),
            'skipped' => $skipped,
        ]);
    }

    /**
     * Igual que bulkDetachProducts, pero para VARIOS productos a la vez, cada
     * uno con su propia lista de temáticas/variantes a desvincular.
     */
    public function bulkDetachMany(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'links' => 'required|array|min:1',
            'links.*.productId' => 'required|exists:products,id',
            'links.*.themeKeys' => 'required|array|min:1',
            'links.*.themeKeys.*' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Detach Many Products from Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $deletedCount = 0;

        foreach ($request->links as $link) {
            $themeKeys = $link['themeKeys'];
            $hasNull = in_array(null, $themeKeys, true);
            $nonNullKeys = array_values(array_filter($themeKeys, fn($k) => $k !== null));

            $deletedCount += ProductPdfDesignProduct::where('product_pdf_design_id', $design->id)
                ->where('product_id', $link['productId'])
                ->where(function ($q) use ($nonNullKeys, $hasNull) {
                    if (!empty($nonNullKeys)) {
                        $q->whereIn('theme_key', $nonNullKeys);
                    }
                    if ($hasNull) {
                        $q->orWhereNull('theme_key');
                    }
                })
                ->delete();
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Detach Many Products from Pdf Design', $request->all(), ['deleted' => $deletedCount]);

        return $this->success($design, 'Vínculos eliminados', ['deleted' => $deletedCount]);
    }

    /**
     * Quita el vínculo entre este diseño y un producto (por el id del vínculo,
     * no del producto, porque un mismo producto podría estar vinculado más de
     * una vez con distintos theme_key).
     */
    public function detachProduct($id, $linkId)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $link = ProductPdfDesignProduct::where('product_pdf_design_id', $design->id)->find($linkId);

        if (!$link) {
            return $this->notFound('El vínculo no existe');
        }

        $link->delete();

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Detach Product from Pdf Design', ['designId' => $id, 'linkId' => $linkId], $design);
        return $this->success($design, 'Producto desvinculado del diseño');
    }

    /**
     * Genera un PDF de muestra con el mismo motor que se usa en la generación
     * real (EtiquetaService::generarEtiquetasDesdeDesign), para que el editor
     * del front pueda previsualizar el diseño sin necesidad de una venta real.
     */
    public function preview(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $firstName = $request->query('firstName', 'NOMBRE');
        $lastName = $request->query('lastName', 'APELLIDO');
        $nombre = trim("{$firstName} {$lastName}");

        $productOrder = (object)[
            'id' => 'preview-' . $design->id,
            'product' => (object)['name' => $design->name],
        ];

        try {
            $paths = EtiquetaService::generarEtiquetasDesdeDesign(
                0,
                $design,
                $productOrder,
                [$nombre],
                null,
                null,
                now(),
                [$firstName],
                [$lastName]
            );
        } catch (\Throwable $e) {
            return $this->error('Error generando la vista previa: ' . $e->getMessage(), 500);
        }

        if (empty($paths[0]) || !file_exists($paths[0])) {
            return $this->error('No se pudo generar la vista previa', 500);
        }

        return response()->file($paths[0]);
    }

    /**
     * Sube una imagen para usar como fondo de una hoja del diseño
     * (data.pages[].sheet.background_image). Solo sube el archivo y devuelve
     * la ruta — el front la guarda donde corresponda dentro del `data` al
     * hacer el POST de actualización del diseño.
     */
    public function uploadBackgroundImage(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:8192',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Upload Pdf Design Background Image', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $file = $request->file('image');
        $path = 'pdf-backgrounds/' . $design->id . '/' . uniqid('bg_') . '.' . $file->getClientOriginalExtension();
        Storage::disk('public_uploads')->put($path, file_get_contents($file));

        $this->logAudit(Auth::user(), 'Upload Pdf Design Background Image', ['designId' => $id], ['path' => $path]);
        return $this->success(['path' => $path], 'Imagen de fondo subida');
    }

    private function rules(): array
    {
        return [
            'labelShapeId' => 'nullable|exists:label_shapes,id',
            'name' => 'required|string|max:255',
            'data' => 'required|array',
            'data.pages' => 'required|array|min:1',
            'data.pages.*.elements' => 'required|array',
            'isPublished' => 'nullable|boolean',
            'statusId' => 'nullable|exists:general_statuses,id',
        ];
    }

    /**
     * Nunca se persiste HTML/markup ejecutable proveniente del editor: el texto
     * de cada elemento (en cada página) se limpia y solo se aceptan tipos/campos
     * reconocidos.
     */
    private function sanitizeDesignData(array $data): array
    {
        if (!empty($data['pages']) && is_array($data['pages'])) {
            $data['pages'] = array_map(function ($page) {
                if (!empty($page['elements']) && is_array($page['elements'])) {
                    $page['elements'] = PdfDesignSanitizer::sanitizeElements($page['elements']);
                }
                if (!empty($page['sheet']) && is_array($page['sheet'])) {
                    $page['sheet'] = PdfDesignSanitizer::sanitizeSheet($page['sheet']);
                }
                return $page;
            }, $data['pages']);
        }

        return $data;
    }
}
