<?php

namespace App\Http\Controllers;

use App\Models\PersonalizationIcon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class PersonalizationIconController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = PersonalizationIcon::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $icons = $query->get();
            $this->logAudit(Auth::user(), 'Get Pesonalization Icons List', $request->all(), $icons);
            return $this->success($icons, 'Iconos obtenidos');
        }

        $icons = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Pesonalization Icons List', $request->all(), $icons);
        $metaData = [
            'current_page' => $icons->currentPage(),
            'last_page' => $icons->lastPage(),
            'per_page' => $icons->perPage(),
            'total' => $icons->total(),
            'from' => $icons->firstItem(),
            'to' => $icons->lastItem(),
        ];        
        return $this->success($icons->items(), 'Iconos obtenidos', $metaData);
    }

    public function store(Request $request)
    {
        if ($request->has('statusId')) {
            $request->merge([
                'statusId' => (int) $request->input('statusId')
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:personalization_icons',
            'icon' => 'required|file|mimes:svg|max:2048',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Configuration Icon', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconFile = $request->file('icon');
            $iconName = 'icons/personalization/' . uniqid('icon_') . '.' . $iconFile->getClientOriginalExtension();
            Storage::disk('public_uploads')->put($iconName, file_get_contents($iconFile));
            $iconPath = $iconName;
        }

        $icon = PersonalizationIcon::create([
            'name' => $request->name,
            'icon' => $iconPath,
            'status_id' => $request->statusId ?? 1, 
        ]);
        $this->logAudit(Auth::user(), 'Store Personalization Icon', $request->all(), $icon);
        
        if ($icon->icon) {
            $icon->icon = $icon->icon;
        }

        return $this->success($icon, 'Icono de personalización creado');
    }

    public function update(Request $request, $id)
    {
        $icon = $this->findObject(PersonalizationIcon::class, $id);
        if ($request->has('statusId')) {
            $request->merge([
                'statusId' => (int) $request->input('statusId')
            ]);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('personalization_icons')->ignore($icon->id),
            ],
            'icon' => 'nullable|file|mimes:svg|max:2048',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Configuration icon', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $iconPath = $icon->icon;
        if ($request->hasFile('icon')) {
            $iconFile = $request->file('icon');
            $iconName = 'icons/icons/' . uniqid('icon_') . '.' . $iconFile->getClientOriginalExtension();
            if (Storage::disk('public_uploads')->put($iconName, file_get_contents($iconFile))) {
                if ($icon->icon && Storage::disk('public_uploads')->exists($icon->icon)) {
                    Storage::disk('public_uploads')->delete($icon->icon);
                }
                $iconPath = $iconName;
            }
        } elseif ($request->has('icon') && is_null($request->input('icon'))) {
            if ($icon->icon && Storage::disk('public_uploads')->exists($icon->icon)) {
                Storage::disk('public_uploads')->delete($icon->icon);
            }
            $iconPath = null;
}

        $icon->name = $request->input('name', $icon->name);
        $icon->icon = $iconPath;
        $icon->status_id = $request->input('statusId', $icon->status_id);
        $icon->save();
        if ($icon->icon) {
            $icon->icon = $icon->icon;
        }

        $this->logAudit(Auth::user(), 'Update Personalization Icon', $request->all(), $icon);
        return $this->success($icon, 'Icono de personalización actualizado');
    }

    public function delete($id)
    {
        $icon = $this->findObject(PersonalizationIcon::class, $id);
        $icon->delete();
        $this->logAudit(Auth::user(), 'Delete Personalization Icon', $id, $icon);
        return $this->success($icon, 'Icono de personalización eliminado');
    }
}
