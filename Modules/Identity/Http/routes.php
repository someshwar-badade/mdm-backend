<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\IdentityController;

Route::get('/health', [IdentityController::class, 'health']);