<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Application\Services\JwtService;
use Modules\Tenancy\Application\Services\TenantContext;

class OrganizationsTest extends TestCase
{
    use RefreshDatabase;

    protected JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        $this->jwtService = $this->app->make(JwtService::class);
    }

    /**
     * Test organization creation endpoint.
     */
    public function test_create_organization(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);

        $token = $this->jwtService->encode([
            'sub' => $user->id,
            'type' => 'user'
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/organizations', [
                'name' => 'New Test Corp',
                'subdomain' => 'newtest',
                'domain' => 'newtest.com'
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'New Test Corp',
                'subdomain' => 'newtest'
            ]);

        // Verify user associated with organization
        $orgId = $response->json('id');
        $this->assertTrue($user->fresh()->organizations->contains($orgId));
    }

    /**
     * Test organization view access controls.
     */
    public function test_view_organization_authorization(): void
    {
        $orgA = Organization::create(['name' => 'Org Alpha']);
        $orgB = Organization::create(['name' => 'Org Beta']);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);

        // Associate user with Org Alpha only
        $user->organizations()->attach($orgA->id);

        $token = $this->jwtService->encode([
            'sub' => $user->id,
            'type' => 'user'
        ]);

        // 1. Can view Org Alpha (membership holds)
        $alphaRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/organizations/{$orgA->id}");

        $alphaRes->assertStatus(200)
            ->assertJsonFragment(['name' => 'Org Alpha']);

        // 2. Cannot view Org Beta (403 Forbidden - no membership)
        $betaRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/organizations/{$orgB->id}");

        $betaRes->assertStatus(403);
    }
}
