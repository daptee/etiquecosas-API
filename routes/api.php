<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;  

// User
Route::get('usuarios', [UserController::class, 'index']);
Route::post('crear/usuario', [UserController::class, 'store']);
Route::put('actualizar/usuario/{id}', [UserController::class, 'update']);
Route::patch('actualizar/contraseña', [UserController::class, 'updatePassword']);
Route::post('actualizar/foto-de-perfil', [UserController::class, 'updatePhoto']);
Route::delete('eliminar/usuario/{id}', [UserController::class, 'delete']);
Route::patch('activar/usuario/{id}', [UserController::class, 'activateUser']);
Route::patch('desactivar/usuario/{id}', [UserController::class, 'deactivateUser']);

