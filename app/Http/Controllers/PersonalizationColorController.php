<?php

namespace App\Http\Controllers;

use App\Models\PersonalizationColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class PersonalizationColorController extends Controller
{
    use FindObject, ApiResponse, Auditable;

   public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = PersonalizationColor::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');
        if (!$perPage) {
            $colors = $query->get();
            $this->logAudit(Auth::user(), 'Get Personalization Colors List', $request->all(), $colors);
            return response()->json([
                'message' => 'Colores de personalización obtenidos',
                'data' => $colors,
                'meta_data' => null,
            ], 200);
        }

        $colors = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $colors->currentPage(),
            'last_page' => $colors->lastPage(),
            'per_page' => $colors->perPage(),
            'total' => $colors->total(),
            'from' => $colors->firstItem(),
            'to' => $colors->lastItem(),
        ];
        $this->logAudit(Auth::user(), 'Get Personalization Colors List', $request->all(), $colors);
        return response()->json([
            'message' => 'Colores de personalización obtenidos',
            'data' => $colors->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:personalization_colors',
            'colorCode' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Personalization Color', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $color = PersonalizationColor::create([
            'name' => $request->name,
            'color_code' => $request->colorCode,
        ]);
        $this->logAudit(Auth::user(), 'Store Personalization Color', $request->all(), $color);
        return $this->success($color, 'Color de personalización creado', 201);
    }

    public function update(Request $request, $id)
    {
        $color = $this->findObject(PersonalizationColor::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:personalization_colors,name,' . $color->id,
            'colorCode' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Personalization Color', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $color->name = $request->input('name', $color->name);
        $color->color_code = $request->input('colorCode', $color->colorCode);
        $color->save();
        $this->logAudit(Auth::user(), 'Update Personalization Color', $request->all(), $color);
        return $this->success($color, 'Color de personalización actualizado');
    }

    public function delete($id)
    {
        $color = $this->findObject(PersonalizationColor::class, $id);
        $color->delete();
        $this->logAudit(Auth::user(), 'Delete Personalization Color', $id, $color);
        return $this->success($color, 'Color de personalización eliminado');
    }
}
