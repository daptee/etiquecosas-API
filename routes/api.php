<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;

// Auth
Route::post('login', [LoginController::class, 'login']);
Route::post('forgot-password', [LoginController::class, 'forgotPassword']);

Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    // User
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::patch('/update-password', [UserController::class, 'updatePassword']);
    Route::post('/update-photo', [UserController::class, 'updatePhoto']);
    Route::delete('/{id}', [UserController::class, 'delete']);
    Route::patch('/change-status/{id}', [UserController::class, 'changeStatus']);
});
