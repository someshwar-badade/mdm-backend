<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\AuditController;

Route::get('/audit/health', [AuditController::class, 'health']);

// Admin routes scoped under /api/v1/audit-logs
Route::middleware(['auth.jwt', 'tenant.resolve'])->group(function () {
    Route::get('/audit-logs', [AuditController::class, 'index']);
});