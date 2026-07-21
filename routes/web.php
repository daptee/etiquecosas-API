<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Sirve archivos de fuente a través de Laravel para que el middleware CORS aplique.
// Solo necesario si nginx/apache no están configurados para agregar headers CORS en fonts/*.
Route::get('/fonts/{path}', function ($path) {
    $fullPath = public_path('fonts/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'eot'   => 'application/vnd.ms-fontobject',
    ];
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    return response()->file($fullPath, ['Content-Type' => $mime]);
})->where('path', '.*');
