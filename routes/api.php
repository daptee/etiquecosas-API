<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\ConfigurationTagController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientTypeController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\PersonalizationColorController; 
use App\Http\Controllers\PersonalizationIconController; 
use App\Http\Controllers\ShippingTemplateController;
use App\Http\Controllers\TemplateCategoryController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\ProductStatusController;
use App\Http\Controllers\ProductStockStatusController; 
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CouponController;

// Auth
Route::post('login', [LoginController::class, 'login']);
Route::post('forgot-password', [LoginController::class, 'forgotPassword']);
Route::post('client-login', [LoginController::class, 'clientLogin']);
Route::post('client-forgot-password', [LoginController::class, 'clientForgotPassword']);
Route::post('create-admin-user', [UserController::class, 'store']);

// Publica
Route::prefix('v1')->group(function () {
    Route::get('categories', [CategoryController::class, 'getPublicCategories']);
});

// User
Route::middleware('jwt.auth')->prefix('users')->group(function () {
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
    Route::delete('/{id}', [CategoryController::class, 'delete']);
});

// Attribute
Route::middleware('jwt.auth')->prefix('attributes')->group(function () {
    Route::get('/', [AttributeController::class, 'index']);
    Route::post('/', [AttributeController::class, 'store']);
    Route::put('/{id}', [AttributeController::class, 'update']);
    Route::delete('/{id}', [AttributeController::class, 'delete']);
});

// Cost
Route::middleware('jwt.auth')->prefix('costs')->group(function () {
    Route::get('/', [CostController::class, 'index']);
    Route::get('/{id}', [CostController::class, 'show']);
    Route::post('/', [CostController::class, 'store']);   
    Route::put('/{id}', [CostController::class, 'update']);
    Route::delete('/{id}', [CostController::class, 'delete']);

});

// ConfigurationTag
Route::middleware('jwt.auth')->prefix('tags')->group(function () {
    Route::get('/', [ConfigurationTagController::class, 'index']);
    Route::post('/', [ConfigurationTagController::class, 'store']);
    Route::put('/{id}', [ConfigurationTagController::class, 'update']);
    Route::delete('/{id}', [ConfigurationTagController::class, 'delete']);
});

// Profile
Route::middleware('jwt.auth')->prefix('profiles')->group(function () {
    Route::get('/', [ProfileController::class, 'index']);
    Route::post('/', [ProfileController::class, 'store']);
    Route::put('/{id}', [ProfileController::class, 'update']);
});

// Client
Route::middleware('jwt.auth')->prefix('clients')->group(function () {
    Route::get('/', [ClientController::class, 'index']);    
    Route::get('/{id}', [ClientController::class, 'show']);
    Route::post('/', [ClientController::class, 'store']);
    Route::put('/{id}', [ClientController::class, 'update']);
    Route::delete('/{id}', [ClientController::class, 'delete']);
});

// ClientType
Route::middleware('jwt.auth')->prefix('client-types')->group(function () {
    Route::get('/', [ClientTypeController::class, 'index']);
});
  
// Province
Route::middleware('jwt.auth')->prefix('provinces')->group(function () {
    Route::get('/', [ProvinceController::class, 'index']);
});

// PersonalizationColor
Route::middleware('jwt.auth')->prefix('colors')->group(function () {
    Route::get('/', [PersonalizationColorController::class, 'index']);
    Route::post('/', [PersonalizationColorController::class, 'store']);
    Route::put('/{id}', [PersonalizationColorController::class, 'update']);
    Route::delete('/{id}', [PersonalizationColorController::class, 'delete']);
});  

// PersonalizationIcon
Route::middleware('jwt.auth')->prefix('icons')->group(function () {
    Route::get('/', [PersonalizationIconController::class, 'index']);
    Route::post('/', [PersonalizationIconController::class, 'store']);
    Route::put('/{id}', [PersonalizationIconController::class, 'update']);
    Route::delete('/{id}', [PersonalizationIconController::class, 'delete']);
});

// ShippingTemplate
Route::middleware('jwt.auth')->prefix('shipping-templates')->group(function () {
    Route::get('/', [ShippingTemplateController::class, 'index']);
    Route::post('/', [ShippingTemplateController::class, 'store']);
    Route::put('/{id}', [ShippingTemplateController::class, 'update']);
    Route::delete('/{id}', [ShippingTemplateController::class, 'delete']);
});

// TemplateCategory
Route::middleware('jwt.auth')->prefix('template-categories')->group(function () {
    Route::get('/', [TemplateCategoryController::class, 'index']);
});

// ProductType
Route::middleware('jwt.auth')->prefix('product-types')->group(function () {
    Route::get('/', [ProductTypeController::class, 'index']);
});

// ProductStatus
Route::middleware('jwt.auth')->prefix('product-statuses')->group(function () {
    Route::get('/', [ProductStatusController::class, 'index']);
});

// ProductStockStatus
Route::middleware('jwt.auth')->prefix('product-stock-statuses')->group(function () {
    Route::get('/', [ProductStockStatusController::class, 'index']);
});

// Product
Route::middleware('jwt.auth')->prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'delete']);
});

// Coupon 
Route::middleware('jwt.auth')->prefix('coupons')->group(function () {
    Route::get('/', [CouponController::class, 'index']);
    Route::get('/{id}', [CouponController::class, 'show']);
    Route::post('/', [CouponController::class, 'store']);
    Route::put('/{id}', [CouponController::class, 'update']);
    Route::delete('/{id}', [CouponController::class, 'delete']);
});