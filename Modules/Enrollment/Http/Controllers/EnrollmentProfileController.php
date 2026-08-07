<?php

namespace Modules\Enrollment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Enrollment\Domain\Entities\EnrollmentProfile;
use Modules\Enrollment\Domain\Entities\EnrollmentToken;

class EnrollmentProfileController extends Controller
{
    /**
     * List all enrollment profiles for the current tenant.
     */
    public function index(): JsonResponse
    {
        $profiles = EnrollmentProfile::all();
        return response()->json($profiles);
    }

    /**
     * Create a new enrollment profile and associate it with a default token.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'wifi_ssid' => 'nullable|string|max:255',
            'wifi_password' => 'nullable|string|max:255',
            'wifi_security_type' => 'nullable|string|max:255',
            'android_package_name' => 'nullable|string|max:255',
            'android_checksum' => 'nullable|string|max:255',
            'android_download_url' => 'nullable|string|max:2048',
            'android_extras' => 'nullable|array',
            'is_default' => 'nullable|boolean',
        ]);

        $profile = EnrollmentProfile::create($data);

        // Generate a default enrollment token for this profile
        $token = EnrollmentToken::create([
            'enrollment_profile_id' => $profile->id,
            'token' => 'token_' . Str::random(32),
            'expires_at' => now()->addYear(),
            'max_uses' => 1000,
            'uses_count' => 0,
            'status' => 'active',
        ]);

        return response()->json([
            'profile' => $profile,
            'default_token' => $token->token
        ], 201);
    }

    /**
     * Get the Android provisioning QR code payload for a specific profile.
     */
    public function qrPayload(Request $request, int $id): JsonResponse
    {
        $profile = EnrollmentProfile::findOrFail($id);

        // Find or create an active token for this profile
        $token = EnrollmentToken::where('enrollment_profile_id', $profile->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            $token = EnrollmentToken::create([
                'enrollment_profile_id' => $profile->id,
                'token' => 'token_' . Str::random(32),
                'expires_at' => now()->addYear(),
                'max_uses' => 1000,
                'uses_count' => 0,
                'status' => 'active',
            ]);
        }

        $serverUrl = $request->getSchemeAndHttpHost();
        $payload = $profile->getProvisioningPayload($serverUrl, $token->token);

        return response()->json($payload);
    }
}
