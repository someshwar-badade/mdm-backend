<?php

use Illuminate\Support\Facades\Route;
use Modules\Organizations\Http\Controllers\OrganizationsController;

Route::get('/health', [OrganizationsController::class, 'health']);

// Admin routes scoped under /api/v1/organizations
Route::middleware(['auth.jwt'])->group(function () {
    Route::post('/', [OrganizationsController::class, 'store']);
    Route::get('/{id}', [OrganizationsController::class, 'show']);
});