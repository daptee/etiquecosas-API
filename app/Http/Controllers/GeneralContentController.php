<?php

namespace App\Http\Controllers;

use App\Models\GeneralContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class GeneralContentController extends Controller
{
    use ApiResponse, Auditable;

    /**
     * Muestra el contenido general
     */
    public function show()
    {
        $generalContent = GeneralContent::first();

        if (!$generalContent) {
            return $this->success(null, 'No hay contenido configurado');
        }

        return $this->success($generalContent, 'Contenido obtenido');
    }

    /**
     * Crea el contenido general (solo se puede crear una vez)
     */
    public function store(Request $request)
    {
        // Verificar que no exista ya un registro
        $existingContent = GeneralContent::first();
        if ($existingContent) {
            $this->logAudit(Auth::user(), 'Store General Content', $request->all(), 'Content already exists');
            return $this->error('Ya existe contenido configurado. Use el método de actualización.', 400);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store General Content', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Procesar el contenido (puede venir como string JSON o como objeto/array)
        $contentData = $request->content;

        // Si viene como string JSON, decodificar
        if (is_string($contentData)) {
            $contentData = json_decode($contentData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->validationError(['content' => ['El contenido JSON no es válido.']]);
            }
        }

        // Validar que sea un array
        if (!is_array($contentData)) {
            return $this->validationError(['content' => ['El contenido debe ser un objeto JSON válido.']]);
        }

        // Crear el registro
        $generalContent = GeneralContent::create([
            'content' => $contentData,
        ]);

        // Refrescar desde la base de datos para asegurar el cast correcto
        $generalContent->refresh();

        $this->logAudit(Auth::user(), 'Store General Content', $request->all(), $generalContent);
        return $this->success($generalContent, 'Contenido general creado');
    }

    /**
     * Actualiza el contenido general
     */
    public function update(Request $request)
    {
        $generalContent = GeneralContent::first();

        if (!$generalContent) {
            $this->logAudit(Auth::user(), 'Update General Content', $request->all(), 'No content found');
            return $this->error('No existe contenido configurado. Use el método de creación.', 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update General Content', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Procesar el contenido (puede venir como string JSON o como objeto/array)
        $contentData = $request->content;

        // Si viene como string JSON, decodificar
        if (is_string($contentData)) {
            $contentData = json_decode($contentData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->validationError(['content' => ['El contenido JSON no es válido.']]);
            }
        }

        // Validar que sea un array
        if (!is_array($contentData)) {
            return $this->validationError(['content' => ['El contenido debe ser un objeto JSON válido.']]);
        }

        // Actualizar el registro
        $generalContent->content = $contentData;
        $generalContent->save();

        // Refrescar desde la base de datos para asegurar el cast correcto
        $generalContent->refresh();

        $this->logAudit(Auth::user(), 'Update General Content', $request->all(), $generalContent);
        return $this->success($generalContent, 'Contenido general actualizado');
    }
}
