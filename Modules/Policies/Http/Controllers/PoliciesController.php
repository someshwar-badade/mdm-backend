<?php

namespace Modules\Policies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Policies\Domain\Entities\Policy;
use Modules\Policies\Domain\Entities\PolicyAssignment;
use Modules\Policies\Domain\Entities\DevicePolicyStatus;

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

    /**
     * Admin Endpoint: List all policies.
     */
    public function index(): JsonResponse
    {
        $policies = Policy::all();
        return response()->json($policies);
    }

    /**
     * Admin Endpoint: Assign a policy to a device or enrollment profile.
     */
    public function assign(Request $request, int $id): JsonResponse
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