<?php

namespace Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Audit\Domain\Entities\AuditLog;

class AuditController extends Controller
{
    /**
     * Check health of the module.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'Audit',
            'message' => 'Module Audit is functioning correctly.'
        ]);
    }

    /**
     * Admin Endpoint: List audit logs for the active organization.
     */
    public function index(): JsonResponse
    {
        // BelongsToTenant automatically scopes queries using TenantScope
        $logs = AuditLog::with('user')->orderBy('created_at', 'desc')->get();
        return response()->json($logs);
    }
}