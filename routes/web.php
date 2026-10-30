<?php

use App\Http\Controllers\AiAdvisorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourierTrackingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// Public Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Home redirect
    Route::get('/', function () {
        return redirect()->to(auth()->user()->getRedirectUrl());
    });

    // Main Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('menu:dashboard')
        ->name('dashboard');

    // Live Notifications Feed
    Route::get('/api/notifications', [DashboardController::class, 'notifications'])->name('api.notifications');

    // Warehouse Management (Gudang Utama)
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        // Products Catalog
        Route::middleware('menu:warehouse_products')->group(function () {
            Route::get('/products', [WarehouseController::class, 'products'])->name('products');
            Route::post('/products', [WarehouseController::class, 'storeProduct'])->name('products.store');
            Route::put('/products/{product}', [WarehouseController::class, 'updateProduct'])->name('products.update');
            Route::patch('/products/{product}/toggle-status', [WarehouseController::class, 'toggleProductStatus'])->name('products.toggle-status');
            Route::delete('/products/{product}', [WarehouseController::class, 'destroyProduct'])->name('products.destroy');
        });

        // Stock Mutations
        Route::middleware('menu:warehouse_mutations')->group(function () {
            Route::get('/mutations', [WarehouseController::class, 'mutations'])->name('mutations');
            Route::post('/mutations', [WarehouseController::class, 'storeMutation'])->name('mutations.store');
        });
    });

    // Packing Video Station & Barcode Validation
    Route::prefix('packing')->name('packing.')->group(function () {
        Route::middleware('menu:packing_station')->group(function () {
            Route::get('/', [PackingController::class, 'index'])->name('index');
            Route::post('/check', [PackingController::class, 'checkOrder'])->name('check');
        });

        // Background upload endpoints accessible across all warehouse pages
        Route::post('/init-upload', [PackingController::class, 'initUpload'])->name('initUpload');
        Route::post('/upload-video', [PackingController::class, 'uploadVideo'])->name('upload');

        Route::middleware('menu:packing_history')->group(function () {
            Route::get('/history', [PackingController::class, 'history'])->name('history');
        });
    });

    // Courier & Logistics Tracking (Shopee Guide 4)
    Route::prefix('courier-tracking')->name('courier.')->middleware('menu:courier_tracking')->group(function () {
        Route::get('/', [CourierTrackingController::class, 'index'])->name('index');
        Route::get('/{order}', [CourierTrackingController::class, 'show'])->name('show');
        Route::post('/sync/{order}', [CourierTrackingController::class, 'syncTracking'])->name('sync');
        Route::post('/sync-all', [CourierTrackingController::class, 'syncAllTracking'])->name('sync.all');
        Route::post('/demo-seed', [CourierTrackingController::class, 'generateDemoPackages'])->name('demo.seed');
    });

    // Shopee Open API & Channel Integration
    Route::prefix('shopee')->name('shopee.')->group(function () {
        Route::get('/bg-sync', [ShopeeController::class, 'backgroundSync'])->name('bg-sync');

        Route::middleware('menu:shopee_dashboard')->group(function () {
            Route::get('/', [ShopeeController::class, 'dashboard'])->name('dashboard');
            Route::post('/products/pull', [ShopeeController::class, 'pullProducts'])->name('products.pull');
            Route::post('/map/{product}', [ShopeeController::class, 'mapProduct'])->name('map');
            Route::post('/unmap/{product}', [ShopeeController::class, 'unmapProduct'])->name('unmap');
            Route::post('/sync/{product}', [ShopeeController::class, 'syncProductStock'])->name('sync.product');
            Route::post('/sync-all', [ShopeeController::class, 'syncAllStock'])->name('sync.all');
        });

        Route::middleware('menu:shopee_orders')->group(function () {
            Route::get('/orders', [ShopeeController::class, 'orders'])->name('orders');
            Route::post('/orders/pull', [ShopeeController::class, 'pullOrders'])->name('orders.pull');
            Route::post('/simulate-order', [ShopeeController::class, 'simulateOrder'])->name('simulate.order');
            Route::get('/fetch-products', [ShopeeController::class, 'fetchShopProducts'])->name('fetch.products');
        });

        Route::middleware('menu:shopee_settings')->group(function () {
            Route::get('/settings', [ShopeeController::class, 'settings'])->name('settings');
            Route::post('/settings', [ShopeeController::class, 'updateSettings'])->name('settings.update');
            Route::post('/settings/test-connection', [ShopeeController::class, 'testApiConnection'])->name('settings.test');
            Route::post('/settings/refresh-token', [ShopeeController::class, 'refreshToken'])->name('settings.refresh');
            Route::post('/settings/disconnect', [ShopeeController::class, 'disconnect'])->name('settings.disconnect');
            Route::get('/callback', [ShopeeController::class, 'handleCallback'])->name('callback');
        });
    });

    // AI Sales Advisor & Marketing Chat
    Route::prefix('ai')->name('ai.')->middleware('menu:ai_advisor')->group(function () {
        Route::get('/', [AiAdvisorController::class, 'index'])->name('index');
        Route::post('/analyze', [AiAdvisorController::class, 'analyze'])->name('analyze');
        Route::post('/chat', [AiAdvisorController::class, 'chat'])->name('chat');
        Route::post('/chat/clear', [AiAdvisorController::class, 'clearChat'])->name('chat.clear');
        Route::post('/test-connection', [AiAdvisorController::class, 'testConnection'])->name('test');
    });

    // Role & Menu Access Management
    Route::resource('roles', RoleController::class)
        ->middleware('menu:role_management');

    // User Account Management
    Route::resource('users', UserController::class)
        ->except(['create', 'show', 'edit'])
        ->middleware('menu:user_management');
});

// Public Real-Time Push Bridge Webhook from OpenShopee API (CSRF excluded in bootstrap/app.php)
Route::post('/api/shopee-bridge/webhook', [ShopeeController::class, 'handleWebhook'])->name('shopee.bridge.webhook');
Route::post('/shopee/webhook', [ShopeeController::class, 'handleWebhook'])->name('shopee.webhook');
