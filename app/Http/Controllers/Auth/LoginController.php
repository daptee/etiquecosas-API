<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class LoginController extends Controller
{
    use ApiResponse, Auditable;

   public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required',
    ]);
    if ($validator->fails()) {
        return response()->json(['message' => 'Los datos ingresados no respetan el formato'], 422);
    }

    $credentials = $request->only('email', 'password');
    $user = User::where('email', $credentials['email'])->first();
    if (!$user || !$user->is_active) {
        return response()->json(['message' => 'El usuario y/o la contraseña son incorrectos'], 401);
    }

    if (!$token = JWTAuth::attempt($credentials)) {
        return response()->json(['message' => 'El usuario y/o la contraseña son incorrectos'], 401);
    }

    return response()->json([
        'token' => $token
    ], 200);
}

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();
        if (!$user) {
            return $this->error('No se encontró ningún usuario con ese correo electrónico.', 404);
        }

        $newPassword = Str::random(8);
        $newPasswordAlphanumeric = preg_replace('/[^a-zA-Z0-9]/', '', $newPassword);
        $newPasswordFinal = substr($newPasswordAlphanumeric, 0, 8);
        $hashedPassword = Hash::make($newPasswordFinal);
        $user->password = $hashedPassword;
        $user->save();
        $mailData = [
            'name' => $user->name,
            'password' => $newPasswordFinal,
        ];
        Mail::to($user->email)->send(new ForgotPasswordMail($mailData));
        return $this->success(null, 'Se ha enviado una nueva contraseña a tu correo electrónico.');
    }

    public function clientLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Los datos ingresados no respetan el formato'], 422);
        }

        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];
        if (!$token = auth('client')->attempt($credentials)) {
            return response()->json(['message' => 'El usuario y/o la contraseña son incorrectos'], 401);
        }

        return response()->json([
            'token' => $token
        ], 200);
    }

    public function clientForgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $email = $request->input('email');
        $client = Client::where('email', $email)->first();
        if (!$client) {
            return $this->error('No se encontró ningún cliente con ese correo electrónico.', 404);
        }

        $newPassword = Str::random(8);
        $newPasswordAlphanumeric = preg_replace('/[^a-zA-Z0-9]/', '', $newPassword);
        $newPasswordFinal = substr($newPasswordAlphanumeric, 0, 8);
        $hashedPassword = Hash::make($newPasswordFinal);
        $client->password = $hashedPassword;
        $client->save();
        $mailData = [
            'name' => $client->name,
            'password' => $newPasswordFinal,
        ];
        Mail::to($client->email)->send(new ForgotPasswordMail($mailData));
        return $this->success(null, 'Se ha enviado una nueva contraseña a tu correo electrónico.');
    }
}