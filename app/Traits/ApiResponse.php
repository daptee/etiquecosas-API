<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success($data = null, string $message = 'Operacion exitosa', $metaData = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => $data,
            'metaData' => $metaData,
        ], $code);
    }

    protected function error(string $message = 'Error en la solicitud', int $code = 500, $data = null): JsonResponse
    {
        if ($data != null) {
            return response()->json([
                'message' => $message,
                'data'    => $data,
            ], $code);
        } else {
            return response()->json([
                'message' => $message,
            ], $code);
        }
    }

    protected function notFound(string $message = 'No encontrado'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function validationError($errors, string $message = 'Error de validacion'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors'    => $errors,
        ], 422);
    }
}
