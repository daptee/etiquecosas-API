<?php

use App\Http\Controllers\Auth\LoginClientController;
use App\Http\Controllers\CacheController;
use App\Http\Controllers\ClientAddressController;
use App\Http\Controllers\ClientWholesaleController;
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
use App\Http\Controllers\BackupController;
use App\Http\Controllers\PdfDirectoryController;
use App\Http\Controllers\HomeContentController;
use App\Http\Controllers\GeneralContentController;
use App\Http\Controllers\InstructiveController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\FacebookFeedController;
use App\Http\Controllers\ContactController;

// cache
Route::get('/clear-cache', [CacheController::class, 'clearCache'])->name('clearCache');
Route::get('/run', [BackupController::class, 'createBackup']);
Route::get('/clean', [BackupController::class, 'cleanOldBackups']);
Route::get('/notify-production', [BackupController::class, 'notifyProductionOrders']);

// Auth
Route::post('login', [LoginController::class, 'login']);
Route::post('forgot-password', [LoginController::class, 'forgotPassword']);
Route::post('create-admin-user', [UserController::class, 'store']);

// Facebook/Meta Catalog Feed - Public route (outside v1 prefix for clean URL)
Route::get('/meta-catalog', [FacebookFeedController::class, 'generateFeed'])->name('meta.catalog');

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
    Route::post('products/consult-price', [ProductController::class, 'sendProductInquiry']);

    // Category
    Route::get('categories', [CategoryController::class, 'getPublicCategories']);

    // Province
    Route::get('provinces', [ProvinceController::class, 'index']);

    // Sale
    Route::get('sales/statuses', [SaleController::class, 'allSaleStatus']);
    Route::get('sales/payment-method', [SaleController::class, 'allPaymentMethod']);
    Route::get('sales/channel-sale', [SaleController::class, 'allChannelSale']);
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

    // Home Content
    Route::get('home-content', [HomeContentController::class, 'show']);

    // General Content
    Route::get('general-content', [GeneralContentController::class, 'show']);

    // Instructives (Web - only active)
    Route::get('instructives', [InstructiveController::class, 'getActive']);

    // Customer Services (Web - only active)
    Route::get('customer-services', [CustomerServiceController::class, 'getActive']);

    // Contact Form
    Route::post('contact', [ContactController::class, 'sendContactForm']);
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
    Route::post('/bulk-assign-categories', [ProductController::class, 'bulkAssignCategories']);
    Route::post('/apply-text-template', [ProductController::class, 'applyTextTemplate']);
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
    Route::get('/dashboard-stats', [SaleController::class, 'getDashboardStats']);
    Route::get('/export', [SaleController::class, 'exportExcel']);
    Route::get('/{id}', [SaleController::class, 'show']);
    Route::put('/assign-user-sale-multiple', [SaleController::class, 'assignUserToMultipleSales']);
    Route::put('/{id}', [SaleController::class, 'update']);
    Route::put('/change-status-admin/{id}', [SaleController::class, 'changeStatusAdmin']);
    Route::put('/internal_comments/{id}', [SaleController::class, 'updateInternalComment']);
    Route::put('/customer_notes/{id}', [SaleController::class, 'updateCustomerNotes']);
    Route::put('/client-data/{id}', [SaleController::class, 'updateClientData']);
    Route::put('/assign-user/{id}', [SaleController::class, 'assignUser']);
    Route::put('/associate/{id}', [SaleController::class, 'associateSale']);
    Route::put('/remove-association/{id}', [SaleController::class, 'removeAssociation']);
    Route::post('/local', [SaleController::class, 'storeLocalSale']);
    Route::put('/local/{id}', [SaleController::class, 'updateLocalSale']);
    Route::get('/generate-pdf/{id}', [SaleController::class, 'generarPdfSale']);
    Route::post('/generate-bulk-pdfs', [SaleController::class, 'generateBulkPdfs']);
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

// PDF Directories - Admin de directorios de PDF
Route::middleware('jwt.auth')->prefix('pdf-directories')->group(function () {
    Route::get('/', [PdfDirectoryController::class, 'index']);                          // GET ALL - Listado de carpetas
    Route::get('/{fecha}', [PdfDirectoryController::class, 'getPdfsByDate']);           // GET ALL PDF de una fecha
    Route::get('/{fecha}/download-zip', [PdfDirectoryController::class, 'downloadCarpetaZip']); // Descargar carpeta en ZIP
    Route::get('/{fecha}/download/{nombrePdf}', [PdfDirectoryController::class, 'downloadPdf']); // Descargar PDF individual
});

// Home Content - Gestión de contenido de la home
Route::middleware('jwt.auth')->prefix('home-content')->group(function () {
    Route::get('/', [HomeContentController::class, 'show']);       // Ver contenido
    Route::post('/', [HomeContentController::class, 'store']);     // Crear contenido (solo una vez)
    Route::post('/update', [HomeContentController::class, 'update']); // Actualizar contenido (POST porque procesa archivos)
});

// General Content - Gestión de contenido general
Route::middleware('jwt.auth')->prefix('general-content')->group(function () {
    Route::get('/', [GeneralContentController::class, 'show']);
    Route::post('/', [GeneralContentController::class, 'store']);     // Crear contenido (solo una vez)
    Route::put('/', [GeneralContentController::class, 'update']);     // Actualizar contenido
});

// Instructives - Gestión de instructivos (Admin)
Route::middleware('jwt.auth')->prefix('instructives')->group(function () {
    Route::get('/', [InstructiveController::class, 'index']);                  // GET ALL con filtros y paginación
    Route::post('/', [InstructiveController::class, 'store']);                 // Crear instructive
    Route::put('/update-positions', [InstructiveController::class, 'updatePositions']); // Actualizar posiciones (drag & drop)
    Route::put('/{id}', [InstructiveController::class, 'update']);             // Actualizar instructive
    Route::put('/{id}/change-status', [InstructiveController::class, 'changeStatus']);  // Cambiar estado
    Route::delete('/{id}', [InstructiveController::class, 'delete']);          // Eliminar (soft delete)
});

// Customer Services (Admin)
Route::middleware('jwt.auth')->prefix('customer-services')->group(function () {
    Route::get('/', [CustomerServiceController::class, 'index']);                  // GET ALL con filtros y paginación
    Route::post('/', [CustomerServiceController::class, 'store']);                 // Crear customer service
    Route::post('/{id}', [CustomerServiceController::class, 'update']);            // Actualizar customer service (POST para files)
    Route::put('/{id}/change-status', [CustomerServiceController::class, 'changeStatus']);  // Cambiar estado
    Route::delete('/{id}', [CustomerServiceController::class, 'delete']);          // Eliminar (soft delete)
});

// Webhook de Mercado Pago (sin autenticación, MP envía notificaciones POST)
Route::match(['GET', 'POST'], '/mercadopago/webhook', [MercadoPagoController::class, 'webhook'])
    ->name('mercadopago.webhook');

Route::get('/mercadopago/success', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
Route::get('/mercadopago/failure', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
Route::get('/mercadopago/pending', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');

// client
Route::middleware('auth:client')->prefix('web')->group(function () {
    // profile
    Route::put('/profile', [ClientController::class, 'updateProfile']);

    // Sale client
    Route::get('/sales-client/history', [SaleClientController::class, 'orderHistory']);
    Route::post('/sales-client/note', [SaleClientController::class, 'requestOrderModification']);
    Route::post('/sales-client/change-address', [SaleClientController::class, 'requestAddressChange']);
    Route::post('/sales-client/claim', [SaleClientController::class, 'requestShippingClaim']);

    // Address client
    Route::get('addresses', [ClientAddressController::class, 'index']);
    Route::post('addresses', [ClientAddressController::class, 'store']);
    Route::put('addresses/{id}', [ClientAddressController::class, 'update']);

    // wholesales client
    Route::get('wholesales', [ClientWholesaleController::class, 'index']);
    Route::post('wholesales', [ClientWholesaleController::class, 'store']);
    Route::put('wholesales/{id}', [ClientWholesaleController::class, 'update']);
});