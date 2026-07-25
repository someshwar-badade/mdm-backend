<?php

namespace Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

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
}