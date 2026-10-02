<?php

namespace App\Http\Controllers;

use App\Models\ProductPdfDesign;
use App\Models\ProductPdfDesignProduct;
use App\Models\ProductVariant;
use App\Services\EtiquetaService;
use App\Services\PdfDesignImportService;
use App\Services\PdfDesignSanitizer;
use Carbon\Carbon;
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

        $data = $this->sanitizeDesignData($request->input('data'));
        if ($layoutErrors = $this->layoutGroupErrors($data)) {
            $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $layoutErrors);
            return $this->validationError($layoutErrors);
        }

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId,
            'name' => $request->name,
            'data' => $data,
            'is_published' => $request->boolean('isPublished'),
            'status_id' => $request->statusId ?? 1,
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF creado');
    }

    /**
     * Sube un PDF YA GENERADO por este mismo backend (una hoja de etiquetas
     * del sistema viejo, product_pdf/vistas por temática) y arma un diseño
     * nuevo para el editor a partir de lo que detecta ahí: fondos, íconos y
     * texto, con su posición/tamaño/color real. Los íconos quedan con
     * icon_id=1 (placeholder fijo, no hay forma confiable de matchear la
     * imagen real contra el catálogo solo mirando el PDF) y font_id siempre
     * queda null — se completan a mano en el editor. Ver PDF_IMPORTAR_DESDE_PDF.md.
     */
    public function importFromPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'name' => 'required|string|max:255',
            'labelShapeId' => 'nullable|exists:label_shapes,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Import Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $tmpPath = $request->file('pdf')->getRealPath();

        try {
            $data = $this->sanitizeDesignData(PdfDesignImportService::import($tmpPath));
        } catch (\Throwable $e) {
            return $this->error('No se pudo analizar el PDF: ' . $e->getMessage(), 500);
        }

        $elementCount = count($data['pages'][0]['elements'] ?? []);
        if ($elementCount === 0) {
            return $this->error('No se detectó ningún elemento en el PDF — puede que no sea un PDF generado por este sistema', 422);
        }

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId,
            'name' => $request->name,
            'data' => $data,
            'is_published' => false,
            'status_id' => 1,
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Import Pdf Design', ['name' => $request->name], ['designId' => $design->id, 'elementCount' => $elementCount]);
        return $this->success($design, "Diseño importado del PDF ({$elementCount} elementos detectados)");
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

        $data = $design->data;
        if (is_array($request->input('data'))) {
            $data = $this->sanitizeDesignData($request->input('data'));
            if ($layoutErrors = $this->layoutGroupErrors($data)) {
                $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $layoutErrors);
                return $this->validationError($layoutErrors);
            }
        }

        $design->update([
            'label_shape_id' => $request->input('labelShapeId', $design->label_shape_id),
            'name' => $request->input('name', $design->name),
            'data' => $data,
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
            // id de data.pages[].id de ESTE diseño — null = usa todas sus páginas.
            'pageId' => 'nullable|string|max:255',
            'sortOrder' => 'nullable|integer',
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
                'page_id' => $request->pageId,
                'sort_order' => $request->sortOrder ?? 0,
            ]);
        } catch (QueryException $e) {
            return $this->validationError(['themeKey' => ['Ya existe un vínculo idéntico (mismo producto, variante, diseño y página)']]);
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
            // Misma página (o diseño entero) para TODAS las temáticas de esta llamada.
            'pageId' => 'nullable|string|max:255',
            'sortOrder' => 'nullable|integer',
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
                    'page_id' => $request->pageId,
                    'sort_order' => $request->sortOrder ?? 0,
                ]);
                $created[] = $link;
            } catch (QueryException $e) {
                $skipped[] = [
                    'themeKey' => $themeKey,
                    'reason' => 'Ya existe un vínculo idéntico (mismo producto, variante, diseño y página)',
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
            // Misma página (o diseño entero) para todas las temáticas de ESE producto.
            'links.*.pageId' => 'nullable|string|max:255',
            'links.*.sortOrder' => 'nullable|integer',
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
                        'page_id' => $link['pageId'] ?? null,
                        'sort_order' => $link['sortOrder'] ?? 0,
                    ]);
                } catch (QueryException $e) {
                    $skipped[] = [
                        'productId' => $link['productId'],
                        'themeKey' => $themeKey,
                        'reason' => 'Ya existe un vínculo idéntico (mismo producto, variante, diseño y página)',
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

        // Sin esto, cualquier ícono que dependa de la personalización del
        // cliente (editable_field:"icon" o dynamic_attribute_id) sale en
        // blanco en el preview, porque no hay ninguna venta real detrás.
        // ?icon= simula el ícono "libre" (customization_data.icon.icon);
        // ?variantId= carga una variante real para que también resuelvan los
        // íconos por atributo (dynamic_attribute_id).
        $customIcon = $request->query('icon');
        $customColor = $request->query('color');
        $variantId = $request->query('variantId');

        // Mismo motivo que arriba: sin esto, un elemento de texto con
        // dynamic_field "fecha"/"numero_pedido" sale vacío en el preview.
        // ?fecha= (cualquier formato que entienda Carbon::parse) y
        // ?numeroPedido= simulan esos datos de una venta real.
        $fechaPreview = $request->query('fecha') ? Carbon::parse($request->query('fecha')) : now();
        $numeroPedidoPreview = (int) $request->query('numeroPedido', 111111);
        // Para {{id_producto}}: no hay producto comprado detrás del preview.
        $idProductoPreview = (int) $request->query('idProducto', 222222);

        $productOrder = (object)[
            'id' => 'preview-' . $design->id,
            'product_id' => $idProductoPreview,
            'product' => (object)['name' => $design->name],
            'variant' => $variantId ? ProductVariant::find($variantId) : null,
        ];

        // ?format=layout: mismo cálculo que el PDF, pero devuelve las
        // posiciones de los layout_groups en JSON (para comparar contra el
        // editor), sin renderizar nada.
        if ($request->query('format') === 'layout') {
            try {
                $layout = EtiquetaService::calcularLayoutDesdeDesign(
                    $numeroPedidoPreview,
                    $design,
                    $productOrder,
                    $nombre,
                    $customColor,
                    $customIcon,
                    $fechaPreview,
                    $firstName,
                    $lastName
                );
            } catch (\Throwable $e) {
                return $this->error('Error calculando el layout: ' . $e->getMessage(), 500);
            }

            return response()->json($layout);
        }

        try {
            $paths = EtiquetaService::generarEtiquetasDesdeDesign(
                $numeroPedidoPreview,
                $design,
                $productOrder,
                [$nombre],
                $customColor,
                $customIcon,
                $fechaPreview,
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

    /**
     * Sube una imagen para usar en UN elemento puntual del diseño — como
     * fondo de una etiqueta (data.pages[].elements[].background_image) o como
     * ícono custom (data.pages[].elements[].custom_icon_path). A diferencia de
     * subir un ícono por POST /icons, esto NO crea nada en personalization_icons
     * — es una imagen propia de este diseño, no aparece en GET /api/icons.
     * Solo sube el archivo y devuelve la ruta — el front la guarda en el
     * campo que corresponda dentro del `data` al actualizar el diseño.
     */
    public function uploadElementImage(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:8192',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Upload Pdf Design Element Image', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $file = $request->file('image');
        $path = 'pdf-element-images/' . $design->id . '/' . uniqid('el_') . '.' . $file->getClientOriginalExtension();
        Storage::disk('public_uploads')->put($path, file_get_contents($file));

        $this->logAudit(Auth::user(), 'Upload Pdf Design Element Image', ['designId' => $id], ['path' => $path]);
        return $this->success(['path' => $path], 'Imagen subida');
    }

    private function rules(): array
    {
        return [
            'labelShapeId' => 'nullable|exists:label_shapes,id',
            'name' => 'required|string|max:255',
            'data' => 'required|array',
            'data.pages' => 'required|array|min:1',
            'data.pages.*.elements' => 'nullable|array',
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
                if (!empty($page['layout_groups']) && is_array($page['layout_groups'])) {
                    $page['layout_groups'] = PdfDesignSanitizer::sanitizeLayoutGroups($page['layout_groups']);
                }
                return $page;
            }, $data['pages']);
        }

        return $data;
    }

    /**
     * Errores de data.pages[].layout_groups (ya saneados) en el formato de
     * errores de validación: "data.pages.{i}.layout_groups.{j}" => [mensaje].
     * Vacío = todos los grupos son válidos.
     */
    private function layoutGroupErrors(array $data): array
    {
        $errors = [];

        foreach ($data['pages'] ?? [] as $pageIdx => $page) {
            if (!is_array($page) || !array_key_exists('layout_groups', $page) || $page['layout_groups'] === null) {
                continue;
            }
            if (!is_array($page['layout_groups'])) {
                $errors["data.pages.{$pageIdx}.layout_groups"] = ['layout_groups tiene que ser una lista.'];
                continue;
            }

            $groupErrors = PdfDesignSanitizer::validateLayoutGroups($page['layout_groups'], $page['elements'] ?? []);
            foreach ($groupErrors as $groupIdx => $message) {
                $errors["data.pages.{$pageIdx}.layout_groups.{$groupIdx}"] = [$message];
            }
        }

        return $errors;
    }
}
