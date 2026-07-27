<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Application\Services\JwtService;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Entities\RefreshToken;

class AuthController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Authenticate portal admin and return tokens.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.'
            ], 401);
        }

        // Generate tokens
        $accessToken = $this->jwtService->encode([
            'sub' => $user->id,
            'type' => 'user'
        ]);

        $refreshTokenString = Str::random(64);
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $refreshTokenString,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenString,
            'expires_in' => 900 // 15 mins
        ]);
    }

    /**
     * Rotate refresh token and issue a new access token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $tokenRecord = RefreshToken::where('token', $data['refresh_token'])->first();

        if (!$tokenRecord) {
            return response()->json([
                'message' => 'Invalid refresh token.'
            ], 401);
        }

        // Check expiration
        if ($tokenRecord->expires_at->isPast()) {
            return response()->json([
                'message' => 'Refresh token has expired.'
            ], 401);
        }

        // Reuse detection: if already revoked/used
        if ($tokenRecord->is_revoked) {
            // Revoke all tokens for this user (potential token theft/hijack attempt)
            RefreshToken::where('user_id', $tokenRecord->user_id)->update(['is_revoked' => true]);
            
            return response()->json([
                'message' => 'Security alert: Refresh token has already been used or revoked. All active sessions terminated.'
            ], 401);
        }

        // Revoke the old token (mark it as used)
        $tokenRecord->update(['is_revoked' => true]);

        // Generate new token pair
        $newAccessToken = $this->jwtService->encode([
            'sub' => $tokenRecord->user_id,
            'type' => 'user'
        ]);

        $newRefreshTokenString = Str::random(64);
        RefreshToken::create([
            'user_id' => $tokenRecord->user_id,
            'token' => $newRefreshTokenString,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        return response()->json([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshTokenString,
            'expires_in' => 900
        ]);
    }

    /**
     * Revoke refresh token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $tokenRecord = RefreshToken::where('token', $data['refresh_token'])->first();

        if ($tokenRecord) {
            $tokenRecord->update(['is_revoked' => true]);
        }

        return response()->json([
            'message' => 'Successfully logged out.'
        ]);
    }

    /**
     * Fetch the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
