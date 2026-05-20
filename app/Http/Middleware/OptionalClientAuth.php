<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class OptionalClientAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::parseToken();
            $user = $token->authenticate();
            if ($user) {
                auth()->guard('client')->setUser($user);
            }
        } catch (\Throwable $e) {
            // Sin token o token inválido — continúa sin autenticar
        }

        return $next($request);
    }
}
