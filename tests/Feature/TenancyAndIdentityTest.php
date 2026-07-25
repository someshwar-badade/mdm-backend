<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Entities\Role;
use Modules\Tenancy\Application\Services\TenantContext;

class TenancyAndIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear TenantContext before each test
        TenantContext::clear();
    }

    /**
     * Test tenant isolation using TenantScope and BelongsToTenant.
     */
    public function test_tenant_isolation_is_enforced(): void
    {
        // 1. Create two organizations
        $orgA = Organization::create(['name' => 'Organization A']);
        $orgB = Organization::create(['name' => 'Organization B']);

        // 2. Create roles for Org A
        TenantContext::set($orgA->id);
        $roleA1 = Role::create(['name' => 'Admin A', 'slug' => 'admin']);
        $roleA2 = Role::create(['name' => 'User A', 'slug' => 'user']);
        
        // Confirm organization_id was automatically set on creation
        $this->assertEquals($orgA->id, $roleA1->organization_id);
        $this->assertEquals($orgA->id, $roleA2->organization_id);

        // 3. Create roles for Org B
        TenantContext::set($orgB->id);
        $roleB1 = Role::create(['name' => 'Admin B', 'slug' => 'admin']);

        // Confirm organization_id was automatically set
        $this->assertEquals($orgB->id, $roleB1->organization_id);

        // 4. Query roles in Org A context
        TenantContext::set($orgA->id);
        $rolesInA = Role::all();
        $this->assertCount(2, $rolesInA);
        $this->assertTrue($rolesInA->contains($roleA1));
        $this->assertTrue($rolesInA->contains($roleA2));
        $this->assertFalse($rolesInA->contains($roleB1));

        // 5. Query roles in Org B context
        TenantContext::set($orgB->id);
        $rolesInB = Role::all();
        $this->assertCount(1, $rolesInB);
        $this->assertTrue($rolesInB->contains($roleB1));
        $this->assertFalse($rolesInB->contains($roleA1));

        // 6. Query roles without context (Global/System Level)
        TenantContext::clear();
        $allRoles = Role::all();
        $this->assertCount(3, $allRoles);
    }

    /**
     * Test ResolveTenant middleware logic.
     */
    public function test_tenant_resolution_middleware(): void
    {
        // 1. Create Organizations and User
        $orgA = Organization::create(['name' => 'Organization A']);
        $orgB = Organization::create(['name' => 'Organization B']);

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123'
        ]);

        // Link User to Org A but not Org B
        $user->organizations()->attach($orgA->id);

        // 2. Request tenant endpoint without X-Organization-Id header
        $response = $this->actingAs($user)->getJson('/api/v1/tenancy/tenant-only');
        $response->assertStatus(200);
        $response->assertJson(['organization_id' => null]);

        // 3. Request with valid X-Organization-Id header for Org A
        $response = $this->actingAs($user)
            ->withHeader('X-Organization-Id', (string) $orgA->id)
            ->getJson('/api/v1/tenancy/tenant-only');
        $response->assertStatus(200);
        $response->assertJson(['organization_id' => $orgA->id]);

        // 4. Request with unauthorized X-Organization-Id header for Org B
        $response = $this->actingAs($user)
            ->withHeader('X-Organization-Id', (string) $orgB->id)
            ->getJson('/api/v1/tenancy/tenant-only');
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Unauthorized organization access.']);
    }
}
