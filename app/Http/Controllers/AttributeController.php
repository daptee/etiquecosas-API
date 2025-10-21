<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class AttributeController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $query = Attribute::with(['values.generalStatus', 'generalStatus']);
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('statusId', $statusId);
        }

        $query->orderBy('name', 'asc');
        if (!$perPage) {
            $attributes = $query->get();
            $this->logAudit(Auth::user(), 'Get Attributes List', $request->all(), $attributes);
            return $this->success($attributes, 'Atributos obtenidos');
        }

        $attributes = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Attributes List', $request->all(), $attributes);
        $metaData = [
            'current_page' => $attributes->currentPage(),
            'last_page' => $attributes->lastPage(),
            'per_page' => $attributes->perPage(),
            'total' => $attributes->total(),
            'from' => $attributes->firstItem(),
            'to' => $attributes->lastItem(),
        ];
        return $this->success($attributes->items(), 'Atributos obtenidos', $metaData);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:attributes',
            'statusId' => 'nullable|in:1,2',
            'values' => 'nullable|array',
            'values.*.value' => 'required|string|max:255',
            'values.*.statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Attribute', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $attribute = Attribute::create([
            'name' => $request->name,
            'status_id' => $request->statusId ?? 1,
            'attribute_id' => $request->attributeId,
        ]);
        $valuesData = collect($request->input('values'))->map(function ($value) {
            return [
                'value' => $value['value'],
                'status_id' => $value['statusId'] ?? 1,
            ];
        })->toArray();
        $attribute->values()->createMany($valuesData);
        $attribute->load('values.generalStatus', 'generalStatus');
        $this->logAudit(Auth::user(), 'Store Attribute', $request->all(), $attribute);
        return $this->success($attribute, 'Atributo creado');
    }

    public function update(Request $request, $id)
    {
        $attribute = $this->findObject(Attribute::class, $id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
            'statusId' => 'nullable|in:1,2',
            'values' => 'nullable|array',
            'values.*.id' => 'nullable|integer|exists:attribute_values,id',
            'values.*.value' => 'required|string|max:255',
            'values.*.statusId' => 'nullable|in:1,2',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Attribute', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Actualizamos el atributo principal
        $attribute->update([
            'name' => $request->input('name', $attribute->name),
            'status_id' => $request->input('statusId', $attribute->status_id),
        ]);

        // Procesamos los valores
        $submittedValues = collect($request->input('values', []));
        $existingIds = $attribute->values()->pluck('id')->toArray();
        $submittedIds = $submittedValues->pluck('id')->filter()->toArray();

        // Detectar eliminados
        $valuesToDelete = array_diff($existingIds, $submittedIds);

        // Actualizar y crear
        foreach ($submittedValues as $data) {
            if (!empty($data['id']) && in_array($data['id'], $existingIds)) {
                // Actualizar value existente
                $attribute->values()->where('id', $data['id'])->update([
                    'value' => $data['value'],
                    'status_id' => $data['statusId'] ?? 1,
                ]);
            } else {
                // Crear nuevo value
                $attribute->values()->create([
                    'value' => $data['value'],
                    'status_id' => $data['statusId'] ?? 1,
                ]);
            }
        }

        // Eliminar los que no se enviaron
        if (!empty($valuesToDelete)) {
            $attribute->values()->whereIn('id', $valuesToDelete)->delete();
        }

        // Recargar relaciones
        $attribute->load('values.generalStatus', 'generalStatus');

        $this->logAudit(Auth::user(), 'Update Attribute', $request->all(), $attribute);

        return $this->success($attribute, 'Atributo actualizado');
    }
    
    public function delete($id)
    {
        $attribute = $this->findObject(Attribute::class, $id);
        $attribute->delete();
        $this->logAudit(Auth::user(), 'Delete Attribute', $id, $attribute);
        return $this->success($attribute, 'Atributo eliminado');
    }
}
