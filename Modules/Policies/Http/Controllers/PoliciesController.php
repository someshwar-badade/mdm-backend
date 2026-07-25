<?php

namespace Modules\Policies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PoliciesController extends Controller
{
    /**
     * Check health of the module.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'Policies',
            'message' => 'Module Policies is functioning correctly.'
        ]);
    }
}