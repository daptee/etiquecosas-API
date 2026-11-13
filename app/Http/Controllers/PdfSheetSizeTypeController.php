<?php

namespace App\Http\Controllers;

use App\Models\PdfSheetSizeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

/**
 * Catálogo de "tipos" de tamaño de hoja (Página, Pliego, ...) — ver
 * PdfSheetSizeController. Tabla separada (en vez de un ENUM fijo) para que
 * se puedan agregar tipos nuevos desde acá, sin tocar el esquema.
 */
class PdfSheetSizeTypeController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = PdfSheetSizeType::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        $query->orderBy('name', 'asc');

        return $this->success($query->get(), 'Tipos de tamaño de hoja obtenidos');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:pdf_sheet_size_types',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Pdf Sheet Size Type', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $type = PdfSheetSizeType::create(['name' => $request->name]);

        $this->logAudit(Auth::user(), 'Store Pdf Sheet Size Type', $request->all(), $type);
        return $this->success($type, 'Tipo de tamaño de hoja creado');
    }

    public function update(Request $request, $id)
    {
        $type = $this->findObject(PdfSheetSizeType::class, $id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('pdf_sheet_size_types')->ignore($type->id)],
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Pdf Sheet Size Type', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $type->update(['name' => $request->name]);

        $this->logAudit(Auth::user(), 'Update Pdf Sheet Size Type', $request->all(), $type);
        return $this->success($type, 'Tipo de tamaño de hoja actualizado');
    }

    public function delete($id)
    {
        $type = $this->findObject(PdfSheetSizeType::class, $id);
        $type->delete();
        $this->logAudit(Auth::user(), 'Delete Pdf Sheet Size Type', $id, $type);
        return $this->success($type, 'Tipo de tamaño de hoja eliminado');
    }
}
