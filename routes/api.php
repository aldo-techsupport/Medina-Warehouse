<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobilePackingController;
use App\Http\Controllers\Api\MobileSystemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile & Warehouse API Routes (v1)
|--------------------------------------------------------------------------
|
| Stateless REST APIs designed for mobile applications (Flutter, React Native,
| Android/iOS) to operate camera barcode scanning and video recording
| seamlessly across local networks without browser SSL limitations.
|
*/

Route::prefix('v1')->group(function () {
    // System status & discovery
    Route::get('/ping', [MobileSystemController::class, 'ping'])->name('api.v1.ping');
    Route::get('/status', [MobileSystemController::class, 'status'])->name('api.v1.status');

    // Authentication & Operator Profiles
    Route::prefix('auth')->group(function () {
        Route::post('/login', [MobileAuthController::class, 'login'])->name('api.v1.auth.login');
        Route::get('/packers', [MobileAuthController::class, 'packers'])->name('api.v1.auth.packers');

        // Protected Auth Routes
        Route::middleware('mobile.auth')->group(function () {
            Route::post('/logout', [MobileAuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('/me', [MobileAuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    // Scanner & Camera Packing Station APIs (allows optional token or station mode)
    Route::middleware('mobile.auth:optional')->group(function () {
        // Barcode / QR Code Scanner & Validation (Check Packet / Resi)
        Route::post('/scanner/scan', [MobilePackingController::class, 'scan'])->name('api.v1.scanner.scan');
        Route::post('/packing/check', [MobilePackingController::class, 'scan'])->name('api.v1.packing.check');

        // Video Recording & Upload (with intuitive aliases)
        Route::post('/packing/init-upload', [MobilePackingController::class, 'initUpload'])->name('api.v1.packing.init-upload');
        Route::post('/packing/start-record', [MobilePackingController::class, 'initUpload'])->name('api.v1.packing.start-record');
        Route::post('/packing/upload-video', [MobilePackingController::class, 'uploadVideo'])->name('api.v1.packing.upload-video');
        Route::post('/packing/upload', [MobilePackingController::class, 'uploadVideo'])->name('api.v1.packing.upload');

        // Video Archives, Viewer & HTTP 206 Partial Content Streaming
        Route::get('/packing/stats', [MobilePackingController::class, 'stats'])->name('api.v1.packing.stats');
        Route::get('/packing/history', [MobilePackingController::class, 'history'])->name('api.v1.packing.history');
        Route::get('/packing/videos', [MobilePackingController::class, 'videos'])->name('api.v1.packing.videos');
        Route::get('/videos', [MobilePackingController::class, 'videos'])->name('api.v1.videos.list');
        Route::get('/packing/records/{id}', [MobilePackingController::class, 'show'])->name('api.v1.packing.records.show');
        Route::get('/packing/records/{id}/stream', [MobilePackingController::class, 'stream'])->name('api.v1.packing.records.stream');
        Route::get('/packing/records/{id}/video', [MobilePackingController::class, 'stream'])->name('api.v1.packing.records.video');
        Route::get('/videos/{id}', [MobilePackingController::class, 'show'])->name('api.v1.videos.show');
        Route::get('/videos/{id}/stream', [MobilePackingController::class, 'stream'])->name('api.v1.videos.stream');
    });
});

