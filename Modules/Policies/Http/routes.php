<?php

use Illuminate\Support\Facades\Route;
use Modules\Policies\Http\Controllers\PoliciesController;

Route::get('/health', [PoliciesController::class, 'health']);