<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected function findObject(string $modelClass, $id)
    {
        $model = app($modelClass)->find($id);
        if(!$model)
        {
            abort(404, 'El objeto no existe');
        }
        return $model;
    }

    public function index(Request $request)
    {
        $perPage = $request->query('quantity', 10);
        $page = $request->query('page', 1);
        $search = $request->query('search');
        $query = User::query();
        if($search)
        {
            $query->where(function ($q) use ($search)
            {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('lastName', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
        if($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $user = User::create([
            'name' => $request->name,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        return response()->json(['message' => 'Usuario creado', 'user' => $user], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $this->findObject(User::class, $id);
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        if($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $user->name = $request->input('name', $user->name);
        $user->lastName = $request->input('lastName', $user->lastName);
        $user->email = $request->input('email', $user->email);
        if($request->filled('password')){
            $user->password = Hash::make($request->password);
        }
        $user->save();
        return response()->json(['message' => 'Usuario actualizado', 'user' => $user], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        if(!$user)
        {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|min:8',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);
        if($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        if(!Hash::check($request->current_password, $user->password))
        {
            return response()->json(['errors' => ['current_password' => ['La contraseña actual es incorrecta.']]], 422);
        }
        $user->password = Hash::make($request->password);
        $user->save();
        return response()->json(['message' => 'Contraseña actualizada'], 200);
    }

    public function updatePhoto(Request $request)
    {
        $user = Auth::user();
        if(!$user)
        {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        if($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        if($request->hasFile('photo'))
        {
            $file = $request->file('photo');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profile_photos', $filename, 'public'); 
            {
                Storage::disk('public')->delete($user->photo);
            }
            $user->photo = $path;
            $user->save();
            return response()->json(['message' => 'Foto de perfil actualizada', 'photo_url' => Storage::url($path)], 200); // El nombre de la URL también se actualiza
        }
        return response()->json(['message' => 'No se encontro el archivo'], 400);
    }

    public function delete($id)
    {
        $user = $this->findObject(User::class, $id);
        $user->delete();
        return response()->json(['message' => 'Usuario eliminado'], 200);
    }

    public function activateUser($id)
    {
        $user = $this->findObject(User::class, $id);
        $user->is_active = true;
        $user->save();
        return response()->json(['message' => 'Usuario activado', 'user' => $user], 200);
    }

    public function deactivateUser($id)
    {
        $user = $this->findObject(User::class, $id);
        $user->is_active = false;
        $user->save();
        return response()->json(['message' => 'Usuario desactivado', 'user' => $user], 200);
    }
}
