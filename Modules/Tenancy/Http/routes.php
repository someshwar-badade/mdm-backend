<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Controllers\TenancyController;

Route::get('/health', [TenancyController::class, 'health']);