<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CacheController extends Controller
{
    public function clearCache()
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            return response()->json(['message' => 'Cache limpiada correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado, inténtelo más tarde',
                'data' => [
                    'error' => $e->getMessage(),
                    'type' => get_class($e)
                ]
            ], 500);
        }
    }
}
