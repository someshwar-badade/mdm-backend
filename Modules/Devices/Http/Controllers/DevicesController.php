<?php

namespace Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DevicesController extends Controller
{
    /**
     * Check health of the module.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'Devices',
            'message' => 'Module Devices is functioning correctly.'
        ]);
    }
}