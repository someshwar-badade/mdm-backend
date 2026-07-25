<?php

namespace Modules\Commands\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CommandsController extends Controller
{
    /**
     * Check health of the module.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'Commands',
            'message' => 'Module Commands is functioning correctly.'
        ]);
    }
}