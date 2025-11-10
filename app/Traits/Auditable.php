<?php

namespace App\Traits;

use App\Models\Audith;

trait Auditable
{
    public function logAudit($user = null, $requestDetails, $params, $response)
    {
        try {
            $userId = $user ? $user->id : null;

            Audith::create([
                'user_id' => $userId,
                'request' => $requestDetails,
                'params' => json_encode($params),
                'response' => json_encode($response),
                'datetime' => now(),
            ]);
        } catch (\Throwable $e) {
            // Retornar un mensaje amigable y el error técnico en data
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error. Intenta nuevamente más tarde.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}

