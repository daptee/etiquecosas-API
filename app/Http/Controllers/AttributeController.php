<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
            $this->logAudit(Auth::user(), 'Get Attributes List', $request->all(), $attributes->first());
            return $this->success($attributes, 'Atributos obtenidos');
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
        return $this->success($attributes->items(), 'Atributos obtenidos', $metaData);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                          => 'required|string|max:255|unique:attributes',
            'type'                          => 'nullable|in:image,color,icon,tipo,text',
            'statusId'                      => 'nullable|in:1,2',
            'values'                        => 'nullable|array',
            'values.*.value'                => 'required|string|max:255',
            'values.*.statusId'             => 'nullable|in:1,2',
            'values.*.metadata'             => 'nullable|array',
            'values.*.metadata.colors'      => 'nullable|array',
            'values.*.metadata.colors.*'    => 'nullable|string|max:50',
            'values.*.metadata.icons'       => 'nullable|array',
            'values.*.metadata.icons.*'     => 'nullable|string|max:255',
            'values.*.metadata.texts'       => 'nullable|array',
            'values.*.metadata.texts.*'     => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Attribute', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $attribute = Attribute::create([
            'name'      => $request->name,
            'type'      => $request->input('type', 'text'),
            'status_id' => $request->statusId ?? 1,
        ]);

        $valuesData = collect($request->input('values', []))->map(function ($value) {
            return [
                'value'     => $value['value'],
                'metadata'  => isset($value['metadata']) ? json_encode($value['metadata']) : null,
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
            'name'                          => 'required|string|max:255|unique:attributes,name,' . $attribute->id,
            'type'                          => 'nullable|in:image,color,icon,tipo,text',
            'statusId'                      => 'nullable|in:1,2',
            'values'                        => 'nullable|array',
            'values.*.id'                   => 'nullable|integer|exists:attribute_values,id',
            'values.*.value'                => 'required|string|max:255',
            'values.*.statusId'             => 'nullable|in:1,2',
            'values.*.metadata'             => 'nullable|array',
            'values.*.metadata.colors'      => 'nullable|array',
            'values.*.metadata.colors.*'    => 'nullable|string|max:50',
            'values.*.metadata.icons'       => 'nullable|array',
            'values.*.metadata.icons.*'     => 'nullable|string|max:255',
            'values.*.metadata.texts'       => 'nullable|array',
            'values.*.metadata.texts.*'     => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Attribute', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $attribute->update([
            'name'      => $request->input('name', $attribute->name),
            'type'      => $request->input('type', $attribute->type),
            'status_id' => $request->input('statusId', $attribute->status_id),
        ]);

        $submittedValues = collect($request->input('values', []));
        $existingIds = $attribute->values()->pluck('id')->toArray();
        $submittedIds = $submittedValues->pluck('id')->filter()->toArray();

        $valuesToDelete = array_diff($existingIds, $submittedIds);

        foreach ($submittedValues as $data) {
            $updateData = [
                'value'     => $data['value'],
                'metadata'  => isset($data['metadata']) ? json_encode($data['metadata']) : null,
                'status_id' => $data['statusId'] ?? 1,
            ];
            if (!empty($data['id']) && in_array($data['id'], $existingIds)) {
                $attribute->values()->where('id', $data['id'])->update($updateData);
            } else {
                $attribute->values()->create($updateData);
            }
        }

        if (!empty($valuesToDelete)) {
            // Eliminar imágenes de los values que se van a borrar
            foreach ($valuesToDelete as $valueId) {
                $this->deleteValueStoredImages(AttributeValue::find($valueId));
            }
            $attribute->values()->whereIn('id', $valuesToDelete)->delete();
        }

        $attribute->load('values.generalStatus', 'generalStatus');
        $this->logAudit(Auth::user(), 'Update Attribute', $request->all(), $attribute);
        return $this->success($attribute, 'Atributo actualizado');
    }

    public function delete($id)
    {
        $attribute = $this->findObject(Attribute::class, $id);
        // Eliminar imágenes de todos los values antes de borrar el atributo
        foreach ($attribute->values as $value) {
            $this->deleteValueStoredImages($value);
        }
        $attribute->delete();
        $this->logAudit(Auth::user(), 'Delete Attribute', $id, $attribute);
        return $this->success($attribute, 'Atributo eliminado');
    }

    /**
     * Sube una o más imágenes a un attribute value.
     * Acepta multipart: images[] (array de archivos)
     */
    public function uploadValueImages(Request $request, $id)
    {
        $attributeValue = $this->findObject(AttributeValue::class, $id);

        $validator = Validator::make($request->all(), [
            'images'   => 'required|array|min:1',
            'images.*' => 'required|file|image|max:5120',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $metadata = $attributeValue->metadata ?? [];
        $existingImages = $metadata['images'] ?? [];

        foreach ($request->file('images') as $file) {
            $path = 'images/attributes/values/' . uniqid('av_') . '.' . $file->getClientOriginalExtension();
            Storage::disk('public_uploads')->put($path, file_get_contents($file));
            $existingImages[] = $path;
        }

        $metadata['images'] = $existingImages;
        $attributeValue->update(['metadata' => $metadata]);

        $this->logAudit(Auth::user(), 'Upload Attribute Value Images', ['id' => $id], $attributeValue);
        return $this->success($attributeValue->fresh(), 'Imágenes subidas correctamente');
    }

    /**
     * Elimina una imagen puntual del metadata de un attribute value.
     * Body: { "image_path": "images/attributes/values/av_xxx.jpg" }
     */
    public function deleteValueImage(Request $request, $id)
    {
        $attributeValue = $this->findObject(AttributeValue::class, $id);

        $validator = Validator::make($request->all(), [
            'image_path' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $imagePath = $request->input('image_path');
        $metadata = $attributeValue->metadata ?? [];
        $images = $metadata['images'] ?? [];

        if (!in_array($imagePath, $images)) {
            return $this->error('La imagen no existe en este attribute value', 404);
        }

        if (Storage::disk('public_uploads')->exists($imagePath)) {
            Storage::disk('public_uploads')->delete($imagePath);
        }

        $metadata['images'] = array_values(array_filter($images, fn($img) => $img !== $imagePath));
        $attributeValue->update(['metadata' => $metadata]);

        $this->logAudit(Auth::user(), 'Delete Attribute Value Image', ['id' => $id, 'image_path' => $imagePath], $attributeValue);
        return $this->success($attributeValue->fresh(), 'Imagen eliminada correctamente');
    }

    private function deleteValueStoredImages(?AttributeValue $value): void
    {
        if (!$value) return;
        $images = $value->metadata['images'] ?? [];
        foreach ($images as $path) {
            if (Storage::disk('public_uploads')->exists($path)) {
                Storage::disk('public_uploads')->delete($path);
            }
        }
    }
}
