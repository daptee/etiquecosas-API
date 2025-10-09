<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class LoginClientController extends Controller
{
    use ApiResponse, Auditable;

    public function Register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'cuit' => 'nullable|string|max:20|unique:clients,cuit',
            'notifications' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            $this->logAudit(null, 'Store Client', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $client = Client::create([
            'client_type_id' => 1,
            'name' => $request->name,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'password' => $request->password ? bcrypt($request->password) : null,
            'phone' => $request->phone ?? null,
            'cuit' => $request->cuit ?? null, 
            'status_id' => 1,
        ]);
        
        $client->load([
            'clientType'
        ]);
        $mailData = [
            'name' => $client->name . ' ' . $client->lastName
        ];
        Mail::to($client->email)->send(new WelcomeMail($mailData));
        $this->logAudit(null, 'Register Client', $request->all(), $client);
        return $this->success($client, 'Cliente registrado');
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