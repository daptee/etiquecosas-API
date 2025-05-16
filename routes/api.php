<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\ConfigurationTagController;

// Auth
Route::post('login', [LoginController::class, 'login']);
Route::post('forgot-password', [LoginController::class, 'forgotPassword']);

// User
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::patch('/update-profile', [UserController::class, 'updateProfile']);
    Route::patch('/update-password', [UserController::class, 'updatePassword']);
    Route::post('/update-photo', [UserController::class, 'updatePhoto']);
    Route::delete('/{id}', [UserController::class, 'delete']);
    Route::patch('/change-status/{id}', [UserController::class, 'changeStatus']);
});

// Category
Route::middleware('jwt.auth')->prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::put('/{id}', [CategoryController::class, 'update']);
});

// Attribute
Route::middleware('jwt.auth')->prefix('attributes')->group(function () {
    Route::get('/', [AttributeController::class, 'index']);
    Route::post('/', [AttributeController::class, 'store']);
    Route::put('/{id}', [AttributeController::class, 'update']);
});

// Cost
Route::middleware('jwt.auth')->prefix('costs')->group(function () {
    Route::get('/', [CostController::class, 'index']);
    Route::post('/', [CostController::class, 'store']);
    Route::get('/{id}', [CostController::class, 'show']);
    Route::put('/{id}', [CostController::class, 'update']);
});

// ConfigurationTag
Route::middleware('jwt.auth')->prefix('tags')->group(function () {
    Route::get('/', [ConfigurationTagController::class, 'index']);
    Route::post('/', [ConfigurationTagController::class, 'store']);
    Route::put('/{id}', [ConfigurationTagController::class, 'update']);
});
