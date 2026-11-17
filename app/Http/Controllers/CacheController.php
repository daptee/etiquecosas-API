<?php

namespace App\Http\Controllers;

use App\Services\FontCacheService;
use Illuminate\Support\Facades\Artisan;

class CacheController extends Controller
{
    public function clearCache(FontCacheService $fontCacheService)
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            $fonts = $fontCacheService->rebuild();

            return response()->json([
                'message' => 'Cache limpiada correctamente',
                'data' => ['fonts' => $fonts],
            ], 200);
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
