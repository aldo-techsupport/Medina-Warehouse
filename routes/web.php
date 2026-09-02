<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// Redirect home to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Main Dashboard (Gudang Utama)
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Warehouse Management (Gudang Utama)
Route::prefix('warehouse')->name('warehouse.')->group(function () {
    // Products Catalog
    Route::get('/products', [WarehouseController::class, 'products'])->name('products');
    Route::post('/products', [WarehouseController::class, 'storeProduct'])->name('products.store');
    Route::put('/products/{product}', [WarehouseController::class, 'updateProduct'])->name('products.update');
    Route::delete('/products/{product}', [WarehouseController::class, 'destroyProduct'])->name('products.destroy');

    // Stock Mutations
    Route::get('/mutations', [WarehouseController::class, 'mutations'])->name('mutations');
    Route::post('/mutations', [WarehouseController::class, 'storeMutation'])->name('mutations.store');
});

// Packing Video Station & Barcode Validation
Route::prefix('packing')->name('packing.')->group(function () {
    Route::get('/', [PackingController::class, 'index'])->name('index');
    Route::post('/check', [PackingController::class, 'checkOrder'])->name('check');
    Route::post('/upload-video', [PackingController::class, 'uploadVideo'])->name('upload');
    Route::get('/history', [PackingController::class, 'history'])->name('history');
});

// Shopee Open API & Channel Integration
Route::prefix('shopee')->name('shopee.')->group(function () {
    Route::get('/', [ShopeeController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [ShopeeController::class, 'orders'])->name('orders');
    Route::get('/settings', [ShopeeController::class, 'settings'])->name('settings');
    Route::post('/settings', [ShopeeController::class, 'updateSettings'])->name('settings.update');
    Route::get('/callback', [ShopeeController::class, 'handleCallback'])->name('callback');

    // Product Mapping & Stock Push
    Route::post('/map/{product}', [ShopeeController::class, 'mapProduct'])->name('map');
    Route::post('/unmap/{product}', [ShopeeController::class, 'unmapProduct'])->name('unmap');
    Route::post('/sync/{product}', [ShopeeController::class, 'syncProductStock'])->name('sync.product');
    Route::post('/sync-all', [ShopeeController::class, 'syncAllStock'])->name('sync.all');

    // Webhook & Simulator
    Route::post('/webhook', [ShopeeController::class, 'handleWebhook'])->name('webhook');
    Route::post('/simulate-order', [ShopeeController::class, 'simulateOrder'])->name('simulate.order');
});
