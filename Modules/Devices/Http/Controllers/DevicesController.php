<?php

namespace Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Devices\Domain\Entities\Device;
use Modules\Devices\Domain\Entities\DeviceIdentity;
use Modules\Commands\Domain\Entities\Command;
use Modules\Notifications\Infrastructure\Integrations\FcmService;

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
        $devices = Device::with('identity')->get();
        return response()->json($devices);
    }

    public function show(int $id): JsonResponse
    {
        $device = Device::with(['identity', 'statusHistory', 'events'])
            ->findOrFail($id);

        // Find assigned policy (direct or profile fallback)
        $assignment = \Modules\Policies\Domain\Entities\PolicyAssignment::with('policy')
            ->where('device_id', $device->id)
            ->first();

        if (!$assignment && $device->enrollment_profile_id) {
            $assignment = \Modules\Policies\Domain\Entities\PolicyAssignment::with('policy')
                ->where('enrollment_profile_id', $device->enrollment_profile_id)
                ->first();
        }

        $policyStatus = null;
        if ($assignment) {
            $policyStatus = \Modules\Policies\Domain\Entities\DevicePolicyStatus::where('device_id', $device->id)
                ->where('policy_id', $assignment->policy_id)
                ->first();
        }

        // Append to device array representation
        $deviceData = $device->toArray();
        $deviceData['assigned_policy'] = $assignment ? $assignment->policy : null;
        $deviceData['policy_status'] = $policyStatus;

        return response()->json($deviceData);
    }

    /**
     * Admin Endpoint: Queue a remote command for the device.
     */
    public function queueCommand(Request $request, int $id, FcmService $fcmService): JsonResponse
    {
        $data = $request->validate([
            'command' => 'required|string|in:lock,reboot,launch_app,kiosk_mode,disable_camera,uninstall_app,wipe,policy_sync',
            'payload' => 'nullable|string',
        ]);

        $device = Device::findOrFail($id);

        $command = Command::create([
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'command' => $data['command'],
            'payload' => $data['payload'] ?? null,
            'status' => 'pending'
        ]);

        // Audit command triggers
        $device->events()->create([
            'event_type' => 'command_queued',
            'severity' => 'info',
            'payload' => [
                'command_id' => $command->id,
                'command' => $command->command,
                'payload' => $command->payload,
                'user_id' => auth()->id()
            ]
        ]);

        // Trigger real-time FCM notification if device has registered a token
        if (!empty($device->fcm_token)) {
            $fcmService->sendCommandNotification($device->fcm_token, $command->toArray());
        }

        return response()->json([
            'message' => 'Command ' . $data['command'] . ' queued successfully.',
            'command_id' => $command->id
        ], 201);
    }

    /**
     * Admin Endpoint: List all commands queued for a device.
     */
    public function listCommands(int $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $commands = $device->commands()->orderBy('created_at', 'desc')->get();
        return response()->json($commands);
    }

    /**
     * Device Endpoint: Process incoming device heartbeat.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        if (!$device) {
            return response()->json(['message' => 'Forbidden device context.'], 403);
        }

        $data = $request->validate([
            'battery' => 'required|array',
            'battery.level' => 'required|integer',
            'battery.charging' => 'required|boolean',
            'network' => 'required|array',
            'network.type' => 'required|string',
            'network.connected' => 'required|boolean',
            'storage' => 'required|array',
            'storage.totalBytes' => 'required|numeric',
            'storage.availableBytes' => 'required|numeric',
            'agent' => 'required|array',
            'agent.version' => 'required|string',
            'agent.policyVersion' => 'required|integer',
            'location' => 'nullable|array',
            'location.latitude' => 'nullable|numeric',
            'location.longitude' => 'nullable|numeric',
        ]);

        // Update heartbeat timestamp and coordinates
        $updateData = ['last_heartbeat_at' => now()];
        if (isset($data['location'])) {
            $updateData['latitude'] = $data['location']['latitude'] ?? null;
            $updateData['longitude'] = $data['location']['longitude'] ?? null;
        }
        $device->update($updateData);

        // Log heartbeat specs
        $device->events()->create([
            'event_type' => 'heartbeat',
            'severity' => 'info',
            'payload' => $data
        ]);

        return response()->json(['message' => 'Heartbeat acknowledged.']);
    }

    /**
     * Device Endpoint: Update device physical hardware profile/inventory details.
     */
    public function updateInventory(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        if (!$device) {
            return response()->json(['message' => 'Forbidden device context.'], 403);
        }

        $data = $request->validate([
            'serial_number' => 'nullable|string|max:255',
            'imei' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:255',
            'hardware_manufacturer' => 'nullable|string|max:255',
            'hardware_model' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:255',
            'sdk_version' => 'nullable|integer',
        ]);

        // Find or create device identity
        $identity = DeviceIdentity::firstOrNew(['device_id' => $device->id]);
        $identity->fill($data);
        $identity->save();

        return response()->json(['message' => 'Device inventory updated successfully.']);
    }

    /**
     * Device Endpoint: Retrieve list of pending commands.
     */
    public function pendingCommands(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        $commands = Command::where('device_id', $device->id)
            ->where('status', 'pending')
            ->get();

        return response()->json($commands);
    }

    /**
     * Device Endpoint: Acknowledge command delivery.
     */
    public function acknowledgeCommand(Request $request, int $id): JsonResponse
    {
        $device = $request->attributes->get('device');

        $command = Command::where('device_id', $device->id)->findOrFail($id);

        $command->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now()
        ]);

        return response()->json(['message' => 'Command delivery acknowledged.']);
    }

    /**
     * Device Endpoint: Report command execution results.
     */
    public function commandResult(Request $request, int $id): JsonResponse
    {
        $device = $request->attributes->get('device');

        $command = Command::where('device_id', $device->id)->findOrFail($id);

        $data = $request->validate([
            'status' => 'required|string|in:success,failed',
            'result' => 'nullable|array'
        ]);

        $command->update([
            'status' => $data['status'],
            'result' => $data['result'],
            'completed_at' => now()
        ]);

        // Audit log command completion status
        $device->events()->create([
            'event_type' => 'command_completed',
            'severity' => $data['status'] === 'success' ? 'info' : 'warning',
            'payload' => [
                'command_id' => $command->id,
                'command' => $command->command,
                'status' => $command->status,
                'result' => $command->result
            ]
        ]);

        return response()->json(['message' => 'Command outcome reported successfully.']);
    }

    /**
     * Device Endpoint: Register/update Firebase Cloud Messaging token.
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        if (!$device) {
            return response()->json(['message' => 'Forbidden device context.'], 403);
        }

        $data = $request->validate([
            'fcm_token' => 'required|string|max:1000'
        ]);

        $device->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['message' => 'FCM Token updated successfully.']);
    }
}