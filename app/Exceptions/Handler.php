<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Capturar errores de base de datos
        if ($e instanceof QueryException || $e instanceof PDOException) {
            // Log del error técnico para debugging
            Log::error('Database Error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retornar respuesta JSON genérica al usuario
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ocurrió un error inesperado, inténtelo más tarde',
                    'data' => [
                        'error' => $e->getMessage(),
                        'type' => get_class($e),
                        'code' => $e->getCode()
                    ]
                ], 500);
            }
        }

        return parent::render($request, $e);
    }
}
