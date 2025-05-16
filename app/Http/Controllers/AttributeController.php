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
    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $statusId = $request->query('statusId');
        $query = Attribute::query()->with('values.statusId', 'statusId');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('statusId', $statusId);
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
            'statusId' => $request->statusId ?? 1,
        ]);
        $valuesData = collect($request->input('values'))->map(function ($value) {
            return [
                'value' => $value['value'],
                'statusId' => $value['statusId'] ?? 1,
            ];
        })->toArray();
        $attribute->values()->createMany($valuesData);
        $attribute->load('values.status', 'status');
        $this->logAudit(Auth::user(), 'Store Attribute', $request->all(), $attribute);
        return $this->success($attribute, 'Atributo creado', 201);
    }

    public function update(Request $request, Attribute $attribute)
    {
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
                    'statusId' => $data['statusId'] ?? 1,
                ];
            } else {
                $valuesToCreate[] = [
                    'value' => $data['value'],
                    'statusId' => $data['statusId'] ?? 1,
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
}
