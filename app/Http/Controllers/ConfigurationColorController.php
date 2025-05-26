<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ConfigurationColorController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $status = $request->query('statusId');
        $query = ConfigurationColor::query()->with('statusId');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $colors = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Configuration Colors List', $request->all(), $colors);
        $metaData = [
            'current_page' => $colors->currentPage(),
            'last_page' => $colors->lastPage(),
            'per_page' => $colors->perPage(),
            'total' => $colors->total(),
            'from' => $colors->firstItem(),
            'to' => $colors->lastItem(),
        ];
        return response()->json([
            'message' => 'Colores de configuración obtenidos',
            'data' => $colors->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:configuration_colors',
            'color_code' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Configuration Color', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $color = ConfigurationColor::create([
            'name' => $request->name,
            'color_code' => $request->colorCode,
        ]);
        $this->logAudit(Auth::user(), 'Store Configuration Color', $request->all(), $color);
        return $this->success($color, 'Color de configuración creada', 201);
    }

    public function update(Request $request, $id)
    {
        $color = $this->findObject(ConfigurationColor::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:configuration_colors,name,' . $color->id,
            'color_code' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Configuration Color', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $color->name = $request->input('name', $color->name);
        $color->color_code = $request->input('colorCode', $color->color_code);
        $color->save();
        $this->logAudit(Auth::user(), 'Update Configuration Color', $request->all(), $color);
        return $this->success($color, 'Color de configuración actualizado');
    }

    public function delete($id)
    {
        $color = $this->findObject(ConfigurationColor::class, $id);
        $color->delete();
        $this->logAudit(Auth::user(), 'Delete Configuration Color', $id, $color);
        return $this->success($color, 'Color de configuración eliminado');
    }
}
