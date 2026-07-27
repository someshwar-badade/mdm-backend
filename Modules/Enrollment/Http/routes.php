<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Http\Controllers\EnrollmentController;
use Modules\Enrollment\Http\Controllers\EnrollmentProfileController;
use Modules\Enrollment\Http\Controllers\DeviceEnrollmentController;

Route::get('/enrollment/health', [EnrollmentController::class, 'health']);

// Admin routes (JWT authenticated & Tenant resolved)
Route::middleware(['auth.jwt', 'tenant.resolve'])->group(function () {
    Route::post('/enrollment-profiles', [EnrollmentProfileController::class, 'store']);
    Route::get('/enrollment-profiles', [EnrollmentProfileController::class, 'index']);
    Route::get('/enrollment-profiles/{id}/qr', [EnrollmentProfileController::class, 'qrPayload']);
});

// Device agent onboarding routes (Public)
Route::post('/device-enrollment/register', [DeviceEnrollmentController::class, 'register']);
Route::post('/device-enrollment/activate', [DeviceEnrollmentController::class, 'activate']);