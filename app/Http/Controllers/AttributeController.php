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
        $query = Attribute::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('statusId', $statusId);
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $attributes = $query->get();
            return response()->json([
                'message' => 'Atributos obtenidos',
                'data' => $attributes,
                'meta_data' => null,
            ], 200);
        }

        $attributes = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $attributes->currentPage(),
            'last_page' => $attributes->lastPage(),
            'per_page' => $attributes->perPage(),
            'total' => $attributes->total(),
            'from' => $attributes->firstItem(),
            'to' => $attributes->lastItem(),
        ];
        return response()->json([
            'message' => 'Atributos obtenidos',
            'data' => $attributes->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:attributes',
            'values' => 'required|array|min:1',
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
        return $this->success($attribute, 'Atributo creado', 201);
    }

   public function update(Request $request, $id)
    {
        $attribute = $this->findObject(Attribute::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
            'values' => 'required|array|min:1',
            'values.*.id' => 'nullable|integer|exists:attribute_values,id',
            'values.*.value' => 'required|string|max:255',
            'values.*.statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Attribute', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $attribute->update($request->only(['name', 'statusId']));
        $existingValues = $attribute->values()->pluck('id', 'value')->toArray();
        $submittedValues = collect($request->input('values'))->keyBy('value');
        $valuesToUpdate = [];
        $valuesToCreate = [];
        $valuesToDelete = array_diff($existingValues, $submittedValues->pluck('id')->filter()->toArray());
        foreach ($submittedValues as $value => $data) {
            $existingId = $existingValues[$value] ?? null;
            if ($existingId) {
                $valuesToUpdate[$existingId] = [
                    'value' => $data['value'],
                    'status_id' => $data['statusId'] ?? 1,
                ];
            } else {
                $valuesToCreate[] = [
                    'value' => $data['value'],
                    'status_id' => $data['statusId'] ?? 1,
                ];
            }
        }

        foreach ($valuesToUpdate as $id => $data) {
            $attribute->values()->where('id', $id)->update($data);
        }

        if (!empty($valuesToCreate)) {
            $attribute->values()->createMany($valuesToCreate);
        }

        if (!empty($valuesToDelete)) {
            $attribute->values()->whereIn('id', $valuesToDelete)->delete();
        }

        $attribute->load('values.status', 'status');
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
