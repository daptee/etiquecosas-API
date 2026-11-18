<?php

use Illuminate\Http\Request;
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
Route::get('/fonts/{path}', function (Request $request, $path) {
    $mimeTypes = [
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'eot'   => 'application/vnd.ms-fontobject',
    ];

    // Solo archivos de fuente que estén DENTRO de public/fonts (sin esto,
    // "fonts/..%2F..%2F.env" servía cualquier archivo del servidor).
    $fontsDir = realpath(public_path('fonts'));
    $fullPath = realpath(public_path('fonts/' . $path));
    $ext = strtolower(pathinfo((string) $fullPath, PATHINFO_EXTENSION));
    if (!$fontsDir || !$fullPath || !is_file($fullPath)
        || !str_starts_with($fullPath, $fontsDir . DIRECTORY_SEPARATOR)
        || !isset($mimeTypes[$ext])) {
        abort(404);
    }

    // Antes salía solo "Cache-Control: public" (sin max-age): el navegador
    // inventaba su propio vencimiento a partir de Last-Modified (días/semanas)
    // y seguía usando una copia vieja/rota sin volver a preguntar — en prod
    // las tipografías no cargaban en un navegador y en otro sí. Con no-cache
    // el navegador revalida en cada carga (ETag/Last-Modified → 304 sin
    // cuerpo si no cambió) y siempre recibe los headers CORS actuales.
    $response = response()->file($fullPath, [
        'Content-Type'  => $mimeTypes[$ext],
        'Cache-Control' => 'no-cache, public',
        'Vary'          => 'Origin',
    ]);
    $response->setEtag(md5($fullPath . '|' . filemtime($fullPath) . '|' . filesize($fullPath)));
    $response->isNotModified($request);

    return $response;
})->where('path', '.*')
  // Sin sesión/cookies/CSRF: un archivo estático no necesita Set-Cookie
  // (y las respuestas con cookies no las cachea el CDN de Hostinger).
  ->withoutMiddleware([
      \App\Http\Middleware\EncryptCookies::class,
      \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
      \Illuminate\Session\Middleware\StartSession::class,
      \Illuminate\View\Middleware\ShareErrorsFromSession::class,
      \App\Http\Middleware\VerifyCsrfToken::class,
  ]);
