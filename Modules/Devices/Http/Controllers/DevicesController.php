<?php

namespace Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Devices\Domain\Entities\Device;

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

    /**
     * List all devices for the active tenant organization.
     */
    public function index(): JsonResponse
    {
        // BelongsToTenant automatically scopes queries using TenantScope
        $devices = Device::with('identity')->get();
        return response()->json($devices);
    }

    /**
     * Fetch complete device details including identity, status logs, and connection events.
     */
    public function show(int $id): JsonResponse
    {
        $device = Device::with(['identity', 'statusHistory', 'events'])
            ->findOrFail($id);
        return response()->json($device);
    }

    /**
     * Send remote command to the target device.
     */
    public function command(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'command' => 'required|string|in:lock,reboot,launch_app,kiosk_mode',
            'payload' => 'nullable|string',
        ]);

        $device = Device::findOrFail($id);

        // Log the command trigger as a device audit event
        $device->events()->create([
            'event_type' => 'command_triggered',
            'severity' => 'info',
            'payload' => [
                'command' => $data['command'],
                'payload' => $data['payload'] ?? null,
                'user_id' => auth()->id()
            ]
        ]);

        // In a real-world environment, we would trigger an FCM push message here.
        // For development, we queue and log it successfully.
        return response()->json([
            'message' => 'Command ' . $data['command'] . ' triggered successfully.'
        ]);
    }
}