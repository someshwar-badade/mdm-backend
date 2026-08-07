<?php

use Illuminate\Support\Facades\Route;
use Modules\Policies\Http\Controllers\PoliciesController;

Route::get('/policies/health', [PoliciesController::class, 'health']);

// Admin routes scoped under /api/v1/policies
Route::middleware(['auth.jwt', 'tenant.resolve'])->group(function () {
    Route::post('/policies', [PoliciesController::class, 'store']);
    Route::get('/policies', [PoliciesController::class, 'index']);
    Route::put('/policies/{id}', [PoliciesController::class, 'update']);
    Route::delete('/policies/{id}', [PoliciesController::class, 'destroy']);
    Route::post('/policies/{id}/assign', [PoliciesController::class, 'assign']);
});

// Device agent routes scoped under /api/v1/device
Route::middleware(['auth.jwt'])->group(function () {
    Route::get('/device/policy', [PoliciesController::class, 'devicePolicy']);
    Route::post('/device/policy/status', [PoliciesController::class, 'devicePolicyStatus']);
});