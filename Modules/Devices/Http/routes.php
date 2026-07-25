<?php

use Illuminate\Support\Facades\Route;
use Modules\Devices\Http\Controllers\DevicesController;

Route::get('/health', [DevicesController::class, 'health']);