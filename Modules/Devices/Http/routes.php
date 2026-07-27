<?php

use Illuminate\Support\Facades\Route;
use Modules\Devices\Http\Controllers\DeviceAuthController;
use Modules\Devices\Http\Controllers\DevicesController;

Route::get('/health', [DevicesController::class, 'health']);

Route::post('/auth', [DeviceAuthController::class, 'auth']);