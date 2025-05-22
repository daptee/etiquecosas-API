<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ProfileController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $profiles = Profile::paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $profiles->currentPage(),
            'last_page' => $profiles->lastPage(),
            'per_page' => $profiles->perPage(),
            'total' => $profiles->total(),
            'from' => $profiles->firstItem(),
            'to' => $profiles->lastItem(),
        ];
        $this->logAudit(Auth::user(), 'Get Profiles List', $request->all(), $profiles);
        return $this->success([
            'data' => $profiles->items(),
            'meta_data' => $metaData,
        ], 'Perfiles obtenidos');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:profiles',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Profile', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $profile = Profile::create([
            'name' => $request->name,
        ]);
        $this->logAudit(Auth::user(), 'Store Profile', $request->all(), $profile);
        return $this->success($profile, 'Perfil creado', 201);
    }
  
    public function update(Request $request, $id)
    {
        $profile = $this->findObject(Profile::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:profiles,name,' . $profile->id,
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Profile', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $profile->update([
            'name' => $request->name,
        ]);
        $this->logAudit(Auth::user(), 'Update Profile', $request->all(), $profile);
        return $this->success($profile, 'Perfil actualizado');
    }    
}
