<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable; 

class ConfigurationTagController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $status = $request->query('statusId');
        $query = ConfigurationTag::query()->with('statusId');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status) {
            $query->where('statusId', $status);
        }

        $tags = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Configuration Tags List', $request->all(), $tags);
        $metaData = [
            'current_page' => $tags->currentPage(),
            'last_page' => $tags->lastPage(),
            'per_page' => $tags->perPage(),
            'total' => $tags->total(),
            'from' => $tags->firstItem(),
            'to' => $tags->lastItem(),
        ];
        return response()->json([
            'message' => 'Etiquetas de configuración obtenidas',
            'data' => $tags->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:configuration_tags',
            'color' => 'nullable|string|max:50',
            'statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Configuration Tag', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $tag = ConfigurationTag::create([
            'name' => $request->name,
            'color' => $request->color,
            'status_id' => $request->statusId ?? 1,
        ]);
        $this->logAudit(Auth::user(), 'Store Configuration Tag', $request->all(), $tag);
        return $this->success($tag, 'Etiqueta de configuración creada', 201);
    }

    public function update(Request $request, $id)
    {
        $tag = $this->findObject(ConfigurationTag::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:configuration_tags,name,' . $tag->id,
            'color' => 'nullable|string|max:50',
            'statusId' => 'nullable|in:1,2',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Configuration Tag', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $tag->name = $request->input('name', $tag->name);
        $tag->color = $request->input('color', $tag->color);
        $tag->status_id = $request->input('statusId', $tag->statusId);
        $tag->save();
        $this->logAudit(Auth::user(), 'Update Configuration Tag', $request->all(), $tag);
        return $this->success($tag, 'Etiqueta de configuración actualizada');
    }
}
