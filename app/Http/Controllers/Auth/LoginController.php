<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if($validator->fails())
        {
            return response()->json(['message' => 'Los datos ingresados no respetan el formato'], 422);
        }
        $credentials = $request->only('email', 'password');
        if(Auth::attempt($credentials))
        {
            $user = Auth::user();
            $token = $user->createToken('Etiquecosas')->plainTextToken;    
            return response()->json(['token' => $token], 200);
        }
        else
        {
            return response()->json(['message' => 'El usuario y/o la contraseña son incorrectos'], 401);
        }
    }
}