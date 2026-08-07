<?php

namespace Modules\Devices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\Devices\Domain\Entities\Device;
use Modules\Identity\Application\Services\JwtService;

class DeviceAuthController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Authenticate a device and issue a Device Access Token.
     */
    public function auth(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'device_uid' => 'required|string',
            'device_secret' => 'required|string',
        ]);

        // Find the device (queries globally because TenantContext is not set yet)
        $device = Device::where('device_uid', $credentials['device_uid'])->first();

        if (!$device || !in_array($device->status, ['enrolled', 'active'])) {
            return response()->json([
                'message' => 'Device not found, unauthorized, or revoked.'
            ], 401);
        }

        // Verify the device secret
        if (!Hash::check($credentials['device_secret'], $device->device_secret)) {
            return response()->json([
                'message' => 'Invalid device credentials.'
            ], 401);
        }

        // Generate short-lived device access token (e.g. 1 hour for devices)
        $accessToken = $this->jwtService->encode([
            'sub' => $device->device_uid,
            'type' => 'device',
            'org' => $device->organization_id
        ], 3600); // 1 hour expiration

        return response()->json([
            'access_token' => $accessToken,
            'expires_in' => 3600
        ]);
    }
}
