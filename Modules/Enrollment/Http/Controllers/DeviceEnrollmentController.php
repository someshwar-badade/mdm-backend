<?php

namespace Modules\Enrollment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Enrollment\Domain\Entities\EnrollmentToken;
use Modules\Enrollment\Domain\Entities\EnrollmentSession;
use Modules\Devices\Domain\Entities\Device;
use Modules\Devices\Domain\Entities\DeviceIdentity;
use Modules\Devices\Domain\Entities\DeviceStatusHistory;
use Modules\Devices\Domain\Entities\DeviceEvent;
use Modules\Identity\Application\Services\JwtService;

class DeviceEnrollmentController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Step 1: Device Agent registers hardware using an enrollment token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_token' => 'required|string',
            'serial_number' => 'required|string|max:255',
            'hardware_manufacturer' => 'nullable|string|max:255',
            'hardware_model' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:255',
            'sdk_version' => 'nullable|integer',
            'imei' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:255',
        ]);

        // Find the enrollment token globally (before tenant context is resolved)
        $token = EnrollmentToken::where('token', $data['enrollment_token'])->first();

        if (!$token || !$token->isValid()) {
            return response()->json([
                'message' => 'Invalid, expired, or fully used enrollment token.'
            ], 400);
        }

        // Increment the enrollment token uses
        $token->increment('uses_count');

        // Generate temporary device credentials
        $deviceUid = 'dev_' . Str::random(32);
        $tempSecret = Str::random(32);

        // Save session
        $session = EnrollmentSession::create([
            'organization_id' => $token->organization_id,
            'enrollment_token_id' => $token->id,
            'device_uid' => $deviceUid,
            'status' => 'initiated',
            'device_metadata' => [
                'serial_number' => $data['serial_number'],
                'hardware_manufacturer' => $data['hardware_manufacturer'] ?? 'Unknown',
                'hardware_model' => $data['hardware_model'] ?? 'Unknown',
                'os_version' => $data['os_version'] ?? 'Unknown',
                'sdk_version' => $data['sdk_version'] ?? 0,
                'imei' => $data['imei'] ?? null,
                'mac_address' => $data['mac_address'] ?? null,
            ],
            'device_secret' => $tempSecret, // Hashed or plain for session. Hashed in Device table, temp is plain
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'enrollment_session_id' => $session->id,
            'device_uid' => $deviceUid,
            'device_secret' => $tempSecret // Plain-text temporary secret
        ], 201);
    }

    /**
     * Step 2: Device Agent activates the registration and receives access credentials.
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_session_id' => 'required|integer',
            'device_uid' => 'required|string',
        ]);

        // Find the initiated session globally
        $session = EnrollmentSession::where('id', $data['enrollment_session_id'])
            ->where('device_uid', $data['device_uid'])
            ->where('status', 'initiated')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Invalid or expired enrollment session.'
            ], 400);
        }

        // Complete the session
        $session->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        // 1. Create the Device
        $metadata = $session->device_metadata;
        $deviceName = ($metadata['hardware_manufacturer'] ?? 'Android') . ' ' . ($metadata['hardware_model'] ?? 'Device');

        $device = Device::create([
            'organization_id' => $session->organization_id,
            'enrollment_profile_id' => $session->token->enrollment_profile_id,
            'device_uid' => $session->device_uid,
            'name' => $deviceName,
            'device_secret' => $session->device_secret, // Automatically hashed on write in Device model
            'status' => 'active'
        ]);

        // 2. Create the Device Identity (physical details)
        DeviceIdentity::create([
            'device_id' => $device->id,
            'serial_number' => $metadata['serial_number'] ?? null,
            'imei' => $metadata['imei'] ?? null,
            'mac_address' => $metadata['mac_address'] ?? null,
            'hardware_manufacturer' => $metadata['hardware_manufacturer'] ?? null,
            'hardware_model' => $metadata['hardware_model'] ?? null,
            'os_version' => $metadata['os_version'] ?? null,
            'sdk_version' => $metadata['sdk_version'] ?? null,
        ]);

        // 3. Log Device Status History
        DeviceStatusHistory::create([
            'device_id' => $device->id,
            'status' => 'active',
            'reason' => 'Initial device activation via enrollment flow.'
        ]);

        // 4. Log Device Event
        DeviceEvent::create([
            'device_id' => $device->id,
            'event_type' => 'enrollment',
            'severity' => 'info',
            'payload' => [
                'enrollment_session_id' => $session->id,
                'enrollment_token_id' => $session->enrollment_token_id
            ]
        ]);

        // Generate short-lived Device Access Token (JWT)
        $accessToken = $this->jwtService->encode([
            'sub' => $device->device_uid,
            'type' => 'device',
            'org' => $device->organization_id
        ], 3600); // 1 hour

        return response()->json([
            'device_uid' => $device->device_uid,
            'device_secret' => $session->device_secret, // Retains plain-text for initial device key stores
            'access_token' => $accessToken,
            'expires_in' => 3600
        ]);
    }
}
