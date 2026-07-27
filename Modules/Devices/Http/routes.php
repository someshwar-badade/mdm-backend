<?php

use Illuminate\Support\Facades\Route;
use Modules\Devices\Http\Controllers\DeviceAuthController;
use Modules\Devices\Http\Controllers\DevicesController;

Route::get('/devices/health', [DevicesController::class, 'health']);
Route::post('/devices/auth', [DeviceAuthController::class, 'auth']);

// Admin routes (JWT authenticated & Tenant resolved)
Route::middleware(['auth.jwt', 'tenant.resolve'])->group(function () {
    Route::get('/devices', [DevicesController::class, 'index']);
    Route::get('/devices/{id}', [DevicesController::class, 'show']);
    
    Route::post('/devices/{id}/commands', [DevicesController::class, 'queueCommand']);
    Route::get('/devices/{id}/commands', [DevicesController::class, 'listCommands']);
});

// Device agent routes (JWT authenticated)
Route::middleware(['auth.jwt'])->group(function () {
    Route::post('/devices/{id}/heartbeat', [DevicesController::class, 'heartbeat']);
    Route::put('/devices/{id}/inventory', [DevicesController::class, 'updateInventory']);
    
    Route::get('/device/commands/pending', [DevicesController::class, 'pendingCommands']);
    Route::post('/device/commands/{id}/acknowledge', [DevicesController::class, 'acknowledgeCommand']);
    Route::post('/device/commands/{id}/result', [DevicesController::class, 'commandResult']);
});