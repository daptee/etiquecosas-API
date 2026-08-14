<?php

namespace App\Http\Controllers;

use App\Models\LabelShape;
use App\Services\PdfDesignSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class LabelShapeController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $query = LabelShape::with('generalStatus');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $shapes = $query->get();
            return $this->success($shapes, 'Formas de etiqueta obtenidas');
        }

        $shapes = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $shapes->currentPage(),
            'last_page' => $shapes->lastPage(),
            'per_page' => $shapes->perPage(),
            'total' => $shapes->total(),
            'from' => $shapes->firstItem(),
            'to' => $shapes->lastItem(),
        ];
        return $this->success($shapes->items(), 'Formas de etiqueta obtenidas', $metaData);
    }

    public function show($id)
    {
        $shape = $this->findObject(LabelShape::class, $id);
        $shape->load('generalStatus');
        return $this->success($shape, 'Forma de etiqueta obtenida');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:label_shapes',
            'shapeType' => 'required|in:rect,circle,custom',
            'widthCm' => 'required|numeric|min:0.1',
            'heightCm' => 'required|numeric|min:0.1',
            'isSystem' => 'nullable|boolean',
            'data' => 'nullable|array',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Label Shape', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $shape = LabelShape::create([
            'name' => $request->name,
            'shape_type' => $request->shapeType,
            'width_cm' => $request->widthCm,
            'height_cm' => $request->heightCm,
            'is_system' => $request->boolean('isSystem'),
            'data' => self::sanitizeShapeData($request->input('data', [])),
            'status_id' => $request->statusId ?? 1,
        ]);

        $shape->load('generalStatus');
        $this->logAudit(Auth::user(), 'Store Label Shape', $request->all(), $shape);
        return $this->success($shape, 'Forma de etiqueta creada');
    }

    public function update(Request $request, $id)
    {
        $shape = $this->findObject(LabelShape::class, $id);

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255', Rule::unique('label_shapes')->ignore($shape->id)],
            'shapeType' => 'nullable|in:rect,circle,custom',
            'widthCm' => 'nullable|numeric|min:0.1',
            'heightCm' => 'nullable|numeric|min:0.1',
            'isSystem' => 'nullable|boolean',
            'data' => 'nullable|array',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Label Shape', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $shape->update([
            'name' => $request->input('name', $shape->name),
            'shape_type' => $request->input('shapeType', $shape->shape_type),
            'width_cm' => $request->input('widthCm', $shape->width_cm),
            'height_cm' => $request->input('heightCm', $shape->height_cm),
            'is_system' => $request->has('isSystem') ? $request->boolean('isSystem') : $shape->is_system,
            'data' => $request->has('data') ? self::sanitizeShapeData($request->input('data')) : $shape->data,
            'status_id' => $request->input('statusId', $shape->status_id),
        ]);

        $shape->load('generalStatus');
        $this->logAudit(Auth::user(), 'Update Label Shape', $request->all(), $shape);
        return $this->success($shape, 'Forma de etiqueta actualizada');
    }

    public function toggleStatus($id)
    {
        $shape = $this->findObject(LabelShape::class, $id);
        $shape->update([
            'status_id' => $shape->status_id === 1 ? 2 : 1,
        ]);
        $shape->load('generalStatus');
        $this->logAudit(Auth::user(), 'Toggle Label Shape Status', $id, $shape);
        return $this->success($shape, 'Estado actualizado');
    }

    public function delete($id)
    {
        $shape = $this->findObject(LabelShape::class, $id);
        $shape->delete();
        $this->logAudit(Auth::user(), 'Delete Label Shape', $id, $shape);
        return $this->success($shape, 'Forma de etiqueta eliminada');
    }

    /**
     * outline_svg solo admite primitivos SVG de forma (path/rect/circle/polygon),
     * nunca script/foreignObject/atributos on*= — evita persistir markup ejecutable
     * que venga del editor del front.
     */
    private static function sanitizeShapeData(?array $data): ?array
    {
        if (!$data) {
            return $data;
        }

        if (isset($data['outline_svg']) && is_string($data['outline_svg'])) {
            $data['outline_svg'] = PdfDesignSanitizer::sanitizeSvg($data['outline_svg']);
        }

        return $data;
    }
}
