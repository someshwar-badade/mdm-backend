<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Controllers\TenancyController;
use Modules\Tenancy\Application\Services\TenantContext;

Route::get('/health', [TenancyController::class, 'health']);

Route::middleware(['tenant.resolve'])->get('/tenant-only', function () {
    return response()->json([
        'organization_id' => TenantContext::get()
    ]);
});