<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Tenancy\Application\Services\TenantContext;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->header('X-Organization-Id');

        if ($orgId) {
            $user = $request->user();

            if ($user) {
                // Verify the user belongs to the requested organization
                $belongsToOrg = $user->organizations()->where('organizations.id', $orgId)->exists();

                if (!$belongsToOrg) {
                    return response()->json([
                        'message' => 'Unauthorized organization access.'
                    ], Response::HTTP_FORBIDDEN);
                }

                TenantContext::set((int) $orgId);
            }
        }

        return $next($request);
    }
}
