<?php

use Illuminate\Support\Facades\Route;
use Modules\Organizations\Http\Controllers\OrganizationsController;

Route::get('/health', [OrganizationsController::class, 'health']);