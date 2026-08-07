<?php

namespace Modules\Organizations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Organizations\Domain\Entities\Organization;

class OrganizationsController extends Controller
{
    /**
     * Check health of the module.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'module' => 'Organizations',
            'message' => 'Module Organizations is functioning correctly.'
        ]);
    }

    /**
     * Create a new organization and associate the authenticated user with it.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'subdomain' => 'nullable|string|max:255',
            'domain' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        $organization = Organization::create($data);

        // Associate the creator with the new organization
        $organization->users()->attach(auth()->id());

        return response()->json($organization, 201);
    }

    /**
     * Retrieve details of a specific organization.
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();

        // Enforce membership security check
        if (!$user->organizations->contains($id)) {
            return response()->json([
                'message' => 'Unauthorized organization access.'
            ], 403);
        }

        $organization = Organization::findOrFail($id);
        return response()->json($organization);
    }
}