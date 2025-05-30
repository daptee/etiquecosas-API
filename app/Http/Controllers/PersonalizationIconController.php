<?php

namespace App\Http\Controllers;

use App\Models\PersonalizationIcon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class PersonalizationIconController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = PersonalizationIcon::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
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
        return response()->json([
            'message' => 'Iconos de personalización obtenidos',
            'data' => $icons->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:personalization_icons',
            'icon' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Configuration Icon', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $icon = PersonalizationIcon::create([
            'name' => $request->name,
            'icon' => $request->icon,
        ]);
        $this->logAudit(Auth::user(), 'Store Personalization Icon', $request->all(), $icon);
        return $this->success($icon, 'Icono de personalización creado', 201);
    }

    public function update(Request $request, $id)
    {
        $icon = $this->findObject(PersonalizationIcon::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:personalization_icons,name,' . $icon->id,
            'icon' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Configuration icon', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $icon->name = $request->input('name', $icon->name);
        $icon->icon = $request->input('iconCode', $icon->icon);
        $icon->save();
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
