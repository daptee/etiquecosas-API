<?php

namespace App\Http\Controllers;

use App\Models\PdfSheetSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class PdfSheetSizeController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $typeId = $request->query('typeId');
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $query = PdfSheetSize::with(['generalStatus', 'type']);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        if ($typeId) {
            $query->where('type_id', $typeId);
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $sizes = $query->get();
            return $this->success($sizes, 'Tamaños de hoja obtenidos');
        }

        $sizes = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $sizes->currentPage(),
            'last_page' => $sizes->lastPage(),
            'per_page' => $sizes->perPage(),
            'total' => $sizes->total(),
            'from' => $sizes->firstItem(),
            'to' => $sizes->lastItem(),
        ];
        return $this->success($sizes->items(), 'Tamaños de hoja obtenidos', $metaData);
    }

    public function show($id)
    {
        $size = $this->findObject(PdfSheetSize::class, $id);
        $size->load(['generalStatus', 'type']);
        return $this->success($size, 'Tamaño de hoja obtenido');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:pdf_sheet_sizes',
            'widthCm' => 'required|numeric|min:0.1',
            'heightCm' => 'required|numeric|min:0.1',
            'typeId' => 'nullable|exists:pdf_sheet_size_types,id',
            'isSystem' => 'nullable|boolean',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Pdf Sheet Size', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $size = PdfSheetSize::create([
            'name' => $request->name,
            'width_cm' => $request->widthCm,
            'height_cm' => $request->heightCm,
            'type_id' => $request->typeId,
            'is_system' => $request->boolean('isSystem'),
            'status_id' => $request->statusId ?? 1,
        ]);

        $size->load(['generalStatus', 'type']);
        $this->logAudit(Auth::user(), 'Store Pdf Sheet Size', $request->all(), $size);
        return $this->success($size, 'Tamaño de hoja creado');
    }

    public function update(Request $request, $id)
    {
        $size = $this->findObject(PdfSheetSize::class, $id);

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255', Rule::unique('pdf_sheet_sizes')->ignore($size->id)],
            'widthCm' => 'nullable|numeric|min:0.1',
            'heightCm' => 'nullable|numeric|min:0.1',
            'typeId' => 'nullable|exists:pdf_sheet_size_types,id',
            'isSystem' => 'nullable|boolean',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Pdf Sheet Size', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $size->update([
            'name' => $request->input('name', $size->name),
            'width_cm' => $request->input('widthCm', $size->width_cm),
            'height_cm' => $request->input('heightCm', $size->height_cm),
            'type_id' => $request->has('typeId') ? $request->typeId : $size->type_id,
            'is_system' => $request->has('isSystem') ? $request->boolean('isSystem') : $size->is_system,
            'status_id' => $request->input('statusId', $size->status_id),
        ]);

        $size->load(['generalStatus', 'type']);
        $this->logAudit(Auth::user(), 'Update Pdf Sheet Size', $request->all(), $size);
        return $this->success($size, 'Tamaño de hoja actualizado');
    }

    public function toggleStatus($id)
    {
        $size = $this->findObject(PdfSheetSize::class, $id);
        $size->update([
            'status_id' => $size->status_id === 1 ? 2 : 1,
        ]);
        $size->load(['generalStatus', 'type']);
        $this->logAudit(Auth::user(), 'Toggle Pdf Sheet Size Status', $id, $size);
        return $this->success($size, 'Estado actualizado');
    }

    public function delete($id)
    {
        $size = $this->findObject(PdfSheetSize::class, $id);
        $size->delete();
        $this->logAudit(Auth::user(), 'Delete Pdf Sheet Size', $id, $size);
        return $this->success($size, 'Tamaño de hoja eliminado');
    }
}
