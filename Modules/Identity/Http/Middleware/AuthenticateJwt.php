<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Application\Services\JwtService;
use Modules\Identity\Domain\Entities\User;
use Modules\Devices\Domain\Entities\Device;
use Modules\Tenancy\Application\Services\TenantContext;

class AuthenticateJwt
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->jwtService->decode($bearer);

        if (!$payload || !isset($payload['sub']) || !isset($payload['type'])) {
            return response()->json(['message' => 'Invalid or expired token.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($payload['type'] === 'user') {
            $user = User::find($payload['sub']);
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
            }
            Auth::setUser($user);
        } elseif ($payload['type'] === 'device') {
            $device = Device::where('device_uid', $payload['sub'])->first();
            if (!$device || !in_array($device->status, ['enrolled', 'active'])) {
                return response()->json(['message' => 'Device unauthorized or revoked.'], Response::HTTP_UNAUTHORIZED);
            }

            // Set the tenant scope automatically for the device
            TenantContext::set($device->organization_id);

            // Bind the device to request attributes
            $request->attributes->set('device', $device);
        } else {
            return response()->json(['message' => 'Unknown token type.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
