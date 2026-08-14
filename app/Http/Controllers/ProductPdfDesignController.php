<?php

namespace App\Http\Controllers;

use App\Models\ProductPdfDesign;
use App\Services\EtiquetaService;
use App\Services\PdfDesignSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $query = ProductPdfDesign::with(['product', 'labelShape', 'generalStatus']);

        if ($productId) {
            $query->where('product_id', $productId);
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
        $design->load(['product', 'labelShape', 'generalStatus']);
        return $this->success($design, 'Diseño de PDF obtenido');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        try {
            $design = ProductPdfDesign::create([
                'product_id' => $request->productId,
                'label_shape_id' => $request->labelShapeId,
                'theme_key' => $request->themeKey,
                'name' => $request->name,
                'data' => $this->sanitizeDesignData($request->input('data')),
                'is_published' => $request->boolean('isPublished'),
                'status_id' => $request->statusId ?? 1,
            ]);
        } catch (QueryException $e) {
            return $this->validationError(['themeKey' => ['Ya existe un diseño para este producto y esta variante/temática']]);
        }

        $design->load(['product', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF creado');
    }

    public function update(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $rules = $this->rules();
        $rules['productId'] = 'nullable|exists:products,id';
        $rules['name'] = 'nullable|string|max:255';
        $rules['data'] = 'nullable|array';

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        try {
            $design->update([
                'product_id' => $request->input('productId', $design->product_id),
                'label_shape_id' => $request->input('labelShapeId', $design->label_shape_id),
                'theme_key' => $request->input('themeKey', $design->theme_key),
                'name' => $request->input('name', $design->name),
                'data' => $request->has('data') ? $this->sanitizeDesignData($request->input('data')) : $design->data,
                'is_published' => $request->has('isPublished') ? $request->boolean('isPublished') : $design->is_published,
                'status_id' => $request->input('statusId', $design->status_id),
            ]);
        } catch (QueryException $e) {
            return $this->validationError(['themeKey' => ['Ya existe un diseño para este producto y esta variante/temática']]);
        }

        $design->load(['product', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF actualizado');
    }

    public function toggleStatus($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->update([
            'status_id' => $design->status_id === 1 ? 2 : 1,
        ]);
        $design->load(['product', 'labelShape', 'generalStatus']);
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
     * Genera un PDF de muestra con el mismo motor que se usa en la generación
     * real (EtiquetaService::generarEtiquetasDesdeDesign), para que el editor
     * del front pueda previsualizar el diseño sin necesidad de una venta real.
     */
    public function preview(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->load('product');

        $nombre = $request->query('name', 'NOMBRE EJEMPLO');

        $productOrder = (object)[
            'id' => 'preview-' . $design->id,
            'product' => (object)['name' => $design->product->name ?? 'preview'],
        ];

        try {
            $paths = EtiquetaService::generarEtiquetasDesdeDesign(
                0,
                $design,
                $productOrder,
                [$nombre],
                null,
                null,
                now()
            );
        } catch (\Throwable $e) {
            return $this->error('Error generando la vista previa: ' . $e->getMessage(), 500);
        }

        if (empty($paths[0]) || !file_exists($paths[0])) {
            return $this->error('No se pudo generar la vista previa', 500);
        }

        return response()->file($paths[0]);
    }

    private function rules(): array
    {
        return [
            'productId' => 'required|exists:products,id',
            'labelShapeId' => 'nullable|exists:label_shapes,id',
            'themeKey' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'data' => 'required|array',
            'data.elements' => 'required|array',
            'isPublished' => 'nullable|boolean',
            'statusId' => 'nullable|exists:general_statuses,id',
        ];
    }

    /**
     * Nunca se persiste HTML/markup ejecutable proveniente del editor: el texto
     * de cada elemento se limpia y solo se aceptan tipos/campos reconocidos.
     */
    private function sanitizeDesignData(array $data): array
    {
        if (!empty($data['elements']) && is_array($data['elements'])) {
            $data['elements'] = PdfDesignSanitizer::sanitizeElements($data['elements']);
        }

        return $data;
    }
}
