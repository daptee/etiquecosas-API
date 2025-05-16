<?php

namespace App\Traits;

use App\Models\Audith;

trait Auditable
{
    public function logAudit($user = null, $requestDetails, $params, $response)
    {
        $userId = $user ? $user->id : null;
        Audith::create([
            'user_id' => $userId,
            'request' => $requestDetails,
            'params' => json_encode($params),
            'response' => json_encode($response),
            'datetime' => now(),
        ]);
    }
}

