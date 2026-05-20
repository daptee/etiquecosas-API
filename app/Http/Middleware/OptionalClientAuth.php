<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OptionalClientAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();
            if ($token) {
                $client = auth('client')->setToken($token)->authenticate();
                if ($client) {
                    auth('client')->setUser($client);
                }
            }
        } catch (\Throwable $e) {
            // Sin token o token inválido — continúa sin autenticar
        }

        return $next($request);
    }
}
