<?php

use Illuminate\Support\Facades\Route;
use Modules\Devices\Http\Controllers\DeviceAuthController;
use Modules\Devices\Http\Controllers\DevicesController;

Route::get('/health', [DevicesController::class, 'health']);
Route::post('/auth', [DeviceAuthController::class, 'auth']);

// Admin routes scoped under /api/v1/devices
Route::middleware(['auth.jwt', 'tenant.resolve'])->group(function () {
    Route::get('/', [DevicesController::class, 'index']);
    Route::get('/{id}', [DevicesController::class, 'show']);
    Route::post('/{id}/command', [DevicesController::class, 'command']);
});