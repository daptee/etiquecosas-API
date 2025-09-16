<?php

use App\Http\Controllers\Auth\LoginClientController;
use App\Http\Controllers\CacheController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\SaleClientController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ShippingConfigController;
use App\Http\Controllers\ShippingOptionController;
use App\Http\Controllers\ShippingZoneController;
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

// cache
Route::get('/clear-cache', [CacheController::class, 'clearCache'])->name('clearCache');

// Auth
Route::post('login', [LoginController::class, 'login']);
Route::post('forgot-password', [LoginController::class, 'forgotPassword']);
Route::post('create-admin-user', [UserController::class, 'store']);

// Publica
Route::prefix('v1')->group(function () {

    // Auth client
    Route::post('auth/client-login', [LoginClientController::class, 'clientLogin']);
    Route::post('auth/client-forgot-password', [LoginClientController::class, 'clientForgotPassword']);
    Route::post('auth/register', [LoginClientController::class, 'register']);

    // Product
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/best-sellers', [ProductController::class, 'bestSellers']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('products/slug/{slug}', [ProductController::class, 'slug']);

    // Category
    Route::get('categories', [CategoryController::class, 'getPublicCategories']);

    // Province
    Route::get('provinces', [ProvinceController::class, 'index']);

    // Sale
    Route::get('sales/{id}', [SaleController::class, 'showRecort']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::put('sales/change-status/{id}', [SaleController::class, 'changeStatus']);
    Route::post('mercadopago/create-preference', [MercadoPagoController::class, 'createPreference']);

    // Coupons
    Route::patch('coupons/validate', [CouponController::class, 'validateCoupon']);

    // Shipping options
    Route::get('shipping-options/', [ShippingOptionController::class, 'index']);       // Listar todas
    Route::get('shipping-options/{id}', [ShippingOptionController::class, 'show']);

    // Shipping zones
    Route::get('shipping-zones/', [ShippingZoneController::class, 'index']);       // Listar todas
    Route::get('shipping-zones/{id}', [ShippingZoneController::class, 'show']); 

    // Shipping config
    Route::get('shipping-config', [ShippingConfigController::class, 'index']);
    Route::get('shipping-config/{id}', [ShippingConfigController::class, 'show']);
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

// Province
Route::middleware('jwt.auth')->prefix('provinces')->group(function () {
    Route::get('/', [ProvinceController::class, 'index']);
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
    Route::post('/{id}', [ProductController::class, 'update']);
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

// Sales
Route::middleware('jwt.auth')->prefix('sales')->group(function () {
    Route::get('/', [SaleController::class, 'index']);
    Route::get('/{id}', [SaleController::class, 'show']);
    Route::put('/{id}', [SaleController::class, 'update']);
    Route::put('/change-status-admin/{id}', [SaleController::class, 'changeStatusAdmin']);
    Route::put('/internal_comments/{id}', [SaleController::class, 'updateInternalComment']);
    Route::put('/client-data/{id}', [SaleController::class, 'updateClientData']);
    Route::put('/assign-client/{id}', [SaleController::class, 'assignUser']);
    Route::post('/local', [SaleController::class, 'storeLocalSale']);
    Route::put('/local/{id}', [SaleController::class, 'updateLocalSale']);
});

// Sales Client

// Shipping options
Route::middleware('jwt.auth')->prefix('shipping-options')->group(function () {
    Route::get('/', [ShippingOptionController::class, 'index']);       // Listar todas
    Route::get('/{id}', [ShippingOptionController::class, 'show']);   // Ver una
    Route::post('/', [ShippingOptionController::class, 'store']);     // Crear
    Route::put('/{id}', [ShippingOptionController::class, 'update']); // Actualizar
    Route::delete('/{id}', [ShippingOptionController::class, 'destroy']); // Soft delete
    Route::patch('/{id}/toggle-status', [ShippingOptionController::class, 'toggleStatus']); // Activar/Desactivar
});

// Shipping zones
Route::middleware('jwt.auth')->prefix('shipping-zones')->group(function () {
    Route::get('/', [ShippingZoneController::class, 'index']);       // Listar todas
    Route::get('/{id}', [ShippingZoneController::class, 'show']);   // Ver una
    Route::post('/', [ShippingZoneController::class, 'store']);     // Crear
    Route::put('/{id}', [ShippingZoneController::class, 'update']); // Actualizar
    Route::delete('/{id}', [ShippingZoneController::class, 'destroy']); // Soft delete
    Route::patch('/{id}/toggle-status', [ShippingZoneController::class, 'toggleStatus']); // Activar/Desactivar
});

// Shipping config
Route::middleware('jwt.auth')->prefix('shipping-config')->group(function () {
    Route::put('/{id}', [ShippingConfigController::class, 'update']);
});

Route::get('/mercadopago/success', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
Route::get('/mercadopago/failure', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
Route::get('/mercadopago/pending', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');

// client
Route::middleware('auth:client')->prefix('web')->group(function () {

    // Sale client
    Route::get('/sales-client/history', [SaleClientController::class, 'orderHistory']);
    Route::post('/sales-client/note', [SaleClientController::class, 'requestOrderModification']);
    Route::post('/sales-client/change-address', [SaleClientController::class, 'requestAddressChange']);
    Route::post('/sales-client/claim', [SaleClientController::class, 'requestShippingClaim']);
});