<?php

namespace Modules\Policies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Policies\Domain\Entities\Policy;
use Modules\Policies\Domain\Entities\PolicyAssignment;
use Modules\Policies\Domain\Entities\DevicePolicyStatus;
use Modules\Notifications\Infrastructure\Integrations\FcmService;
use Modules\Devices\Domain\Entities\Device;

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

    /**
     * Admin Endpoint: Create a new policy configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'settings' => 'required|array',
        ]);

        $policy = Policy::create([
            'organization_id' => auth()->user()->organizations()->first()->id, // fallback default tenant context
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'settings' => $data['settings'],
            'version' => 1
        ]);

        return response()->json($policy, 201);
    }

    public function index(): JsonResponse
    {
        $policies = Policy::with(['assignments', 'deviceStatuses'])->get();
        return response()->json($policies);
    }

    /**
     * Admin Endpoint: Update a policy configuration.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $policy = Policy::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'settings' => 'required|array',
        ]);

        $policy->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'settings' => $data['settings'],
            'version' => $policy->version + 1
        ]);

        return response()->json($policy);
    }

    /**
     * Admin Endpoint: Delete a policy.
     */
    public function destroy(int $id): JsonResponse
    {
        $policy = Policy::findOrFail($id);
        $policy->delete();

        return response()->json([
            'message' => 'Policy deleted successfully.'
        ]);
    }

    /**
     * Admin Endpoint: Assign a policy to a device or enrollment profile.
     */
    public function assign(Request $request, int $id, FcmService $fcmService): JsonResponse
    {
        $data = $request->validate([
            'device_id' => 'nullable|integer|exists:devices,id',
            'enrollment_profile_id' => 'nullable|integer|exists:enrollment_profiles,id',
        ]);

        if (empty($data['device_id']) && empty($data['enrollment_profile_id'])) {
            return response()->json([
                'message' => 'Must assign to either device_id or enrollment_profile_id.'
            ], 400);
        }

        $policy = Policy::findOrFail($id);

        $assignment = PolicyAssignment::updateOrCreate(
            [
                'organization_id' => $policy->organization_id,
                'device_id' => $data['device_id'] ?? null,
                'enrollment_profile_id' => $data['enrollment_profile_id'] ?? null,
            ],
            [
                'policy_id' => $policy->id
            ]
        );

        // Send FCM notification to target device(s)
        if (!empty($data['device_id'])) {
            $device = Device::find($data['device_id']);
            if ($device && !empty($device->fcm_token)) {
                $fcmService->sendCommandNotification($device->fcm_token, [
                    'command' => 'policy_sync',
                    'command_id' => 'policy_' . $policy->id,
                    'payload' => ''
                ]);
            }
        } elseif (!empty($data['enrollment_profile_id'])) {
            $devices = Device::where('enrollment_profile_id', $data['enrollment_profile_id'])->get();
            foreach ($devices as $device) {
                if (!empty($device->fcm_token)) {
                    $fcmService->sendCommandNotification($device->fcm_token, [
                        'command' => 'policy_sync',
                        'command_id' => 'policy_' . $policy->id,
                        'payload' => ''
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Policy assigned successfully.',
            'assignment_id' => $assignment->id
        ]);
    }

    /**
     * Device Endpoint: Retrieve the active policy configuration for the device.
     */
    public function devicePolicy(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        if (!$device) {
            return response()->json(['message' => 'Forbidden device context.'], 403);
        }

        // 1. Direct device assignment check
        $assignment = PolicyAssignment::where('device_id', $device->id)->first();

        // 2. Fallback to profile assignment check
        if (!$assignment && $device->enrollment_profile_id) {
            $assignment = PolicyAssignment::where('enrollment_profile_id', $device->enrollment_profile_id)->first();
        }

        if (!$assignment) {
            return response()->json(null);
        }

        return response()->json($assignment->policy);
    }

    /**
     * Device Endpoint: Submit policy compliance/status report.
     */
    public function devicePolicyStatus(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        if (!$device) {
            return response()->json(['message' => 'Forbidden device context.'], 403);
        }

        $data = $request->validate([
            'policy_id' => 'required|integer|exists:policies,id',
            'status' => 'required|string|in:compliant,non_compliant,unknown',
            'details' => 'nullable|array'
        ]);

        $status = DevicePolicyStatus::updateOrCreate(
            [
                'organization_id' => $device->organization_id,
                'device_id' => $device->id,
                'policy_id' => $data['policy_id'],
            ],
            [
                'status' => $data['status'],
                'details' => $data['details'] ?? null
            ]
        );

        // Audit policy status updates in device events
        $device->events()->create([
            'event_type' => 'policy_status_sync',
            'severity' => $data['status'] === 'compliant' ? 'info' : 'warning',
            'payload' => [
                'policy_id' => $status->policy_id,
                'status' => $status->status,
                'details' => $status->details
            ]
        ]);

        return response()->json([
            'message' => 'Policy status reported successfully.'
        ]);
    }
}