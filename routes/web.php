<?php

use App\Http\Controllers\AuthController;
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
        return redirect()->route('dashboard');
    });

    // Main Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('menu:dashboard')
        ->name('dashboard');

    // Warehouse Management (Gudang Utama)
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        // Products Catalog
        Route::middleware('menu:warehouse_products')->group(function () {
            Route::get('/products', [WarehouseController::class, 'products'])->name('products');
            Route::post('/products', [WarehouseController::class, 'storeProduct'])->name('products.store');
            Route::put('/products/{product}', [WarehouseController::class, 'updateProduct'])->name('products.update');
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
            Route::post('/upload-video', [PackingController::class, 'uploadVideo'])->name('upload');
        });

        Route::middleware('menu:packing_history')->group(function () {
            Route::get('/history', [PackingController::class, 'history'])->name('history');
        });
    });

    // Shopee Open API & Channel Integration
    Route::prefix('shopee')->name('shopee.')->group(function () {
        Route::middleware('menu:shopee_dashboard')->group(function () {
            Route::get('/', [ShopeeController::class, 'dashboard'])->name('dashboard');
            Route::post('/map/{product}', [ShopeeController::class, 'mapProduct'])->name('map');
            Route::post('/unmap/{product}', [ShopeeController::class, 'unmapProduct'])->name('unmap');
            Route::post('/sync/{product}', [ShopeeController::class, 'syncProductStock'])->name('sync.product');
            Route::post('/sync-all', [ShopeeController::class, 'syncAllStock'])->name('sync.all');
        });

        Route::middleware('menu:shopee_orders')->group(function () {
            Route::get('/orders', [ShopeeController::class, 'orders'])->name('orders');
            Route::post('/simulate-order', [ShopeeController::class, 'simulateOrder'])->name('simulate.order');
        });

        Route::middleware('menu:shopee_settings')->group(function () {
            Route::get('/settings', [ShopeeController::class, 'settings'])->name('settings');
            Route::post('/settings', [ShopeeController::class, 'updateSettings'])->name('settings.update');
            Route::get('/callback', [ShopeeController::class, 'handleCallback'])->name('callback');
        });
    });

    // Role & Menu Access Management
    Route::resource('roles', RoleController::class)
        ->middleware('menu:role_management');

    // User Account Management
    Route::resource('users', UserController::class)
        ->except(['create', 'show', 'edit'])
        ->middleware('menu:user_management');
});

// Public Shopee Webhook (CSRF excluded in bootstrap/app.php)
Route::post('/shopee/webhook', [ShopeeController::class, 'handleWebhook'])->name('shopee.webhook');
