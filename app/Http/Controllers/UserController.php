<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class UserController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = User::with('profile');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $query->paginate($perPage, ['*'], 'page', $page);
        $this->logAudit(Auth::user(), 'Get Users List', $request->all(), $users);    
        $metaData = [
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'from' => $users->firstItem(),
            'to' => $users->lastItem(),           
        ];    
        return response()->json([
            'message' => 'Usuarios obtenidos',
            'data' => $users->items(),
            'meta_data' => $metaData,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'profileId' => 'required',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store User', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_id' => $request->profileId,
        ]);
        $this->logAudit(Auth::user(), 'Store User', $request->all(), $user);
        return $this->success($user, 'Usuario creado', 201);
    }

    public function update(Request $request, $id)
    {
        $user = $this->findObject(User::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'profileId' => 'nullable',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update User', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $user->name = $request->input('name', $user->name);
        $user->lastName = $request->input('lastName', $user->lastName);
        $user->email = $request->input('email', $user->email);
        $user->profile_id = $request->input('profileId', $user->profile_id);
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();
        $this->logAudit(Auth::user(), 'Update User', $request->all(), $user);
        return $this->success($user, 'Usuario actualizado');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Usuario no autenticado', 401);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            $this->logAudit($user, 'Update Profile Failed', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $user->name = $request->input('name', $user->name);
        $user->lastName = $request->input('lastName', $user->lastName);
        $user->email = $request->input('email', $user->email);
        $user->save();
        $this->logAudit($user, 'Update Profile', $request->all(), $user);
        return $this->success($user, 'Perfil actualizado');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|min:8',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Password', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        if (!Hash::check($request->current_password, $user->password)) {
            $this->logAudit(Auth::user(), 'Update Password', $request->all(), ['error' => 'Contraseña actual incorrecta']);
            return $this->validationError(['current_password' => ['La contraseña actual es incorrecta.']]);
        }

        $user->password = Hash::make($request->password);
        $user->save();
        $this->logAudit(Auth::user(), 'Update Password', $request->all(), $user);
        return $this->success($user, 'Contraseña actualizada');
    }

    public function updatePhoto(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('Usuario no autenticado', 401);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Photo', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profile_photos', $filename, 'public');
            Storage::disk('public')->delete($user->photo);
            $user->photo = $path;
            $user->save();
            $this->logAudit(Auth::user(), 'Update Photo', $request->all(), ['photo_url' => Storage::url($path)]);
            return $this->success(['photo_url' => Storage::url($path)], 'Foto de perfil actualizada');
        }

        return $this->error('No se encontro el archivo', 400);
    }

    public function delete($id)
    {
        $user = $this->findObject(User::class, $id);
        $user->delete();
        $this->logAudit(Auth::user(), 'Delete User', ['id' => $id], null);
        return $this->success(null, 'Usuario eliminado');
    }

    public function changeStatus(Request $request, $id)
    {
        $user = $this->findObject(User::class, $id);
        $validator = Validator::make($request->all(), [
            'status' => 'required|bool',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Photo', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }
        $user->is_active = $request->status;
        $user->save();
        $this->logAudit(Auth::user(), 'Change Status User', ['id' => $id], $user);
        return $this->success($user, 'Estado del usuario actualizado');
    }
}
