<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Devices\Domain\Entities\Device;
use Modules\Policies\Domain\Entities\Policy;
use Modules\Policies\Domain\Entities\PolicyAssignment;
use Modules\Policies\Domain\Entities\DevicePolicyStatus;
use Modules\Audit\Domain\Entities\AuditLog;
use Modules\Identity\Application\Services\JwtService;
use Modules\Tenancy\Application\Services\TenantContext;

class PoliciesAndAuditTest extends TestCase
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
     * Test admin policy creation, list, assignment, and device policy sync/status tracking.
     */
    public function test_complete_policies_and_compliance_lifecycle(): void
    {
        $org = Organization::create(['name' => 'Brick Org']);
        
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@brick.com',
            'password' => 'secret123'
        ]);
        $admin->organizations()->attach($org->id);

        $adminToken = $this->jwtService->encode([
            'sub' => $admin->id,
            'type' => 'user'
        ]);

        $device = Device::create([
            'organization_id' => $org->id,
            'device_uid' => 'dev_terminal_882',
            'name' => 'Brick Terminal',
            'status' => 'active',
            'device_secret' => bcrypt('secretpw')
        ]);

        $deviceToken = $this->jwtService->encode([
            'sub' => $device->device_uid,
            'type' => 'device',
            'org' => $org->id
        ]);

        // 1. Admin creates a policy
        $createRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/v1/policies', [
                'name' => 'Strict Security Config',
                'description' => 'Disables cameras and enforces lock screen passwords.',
                'settings' => [
                    'disable_camera' => true,
                    'password_minimum_length' => 6
                ]
            ]);

        $createRes->assertStatus(201)
            ->assertJsonFragment(['name' => 'Strict Security Config']);

        $policyId = $createRes->json('id');

        // 2. Admin retrieves policies list
        $listRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->getJson('/api/v1/policies');

        $listRes->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $policyId]);

        // 3. Admin assigns policy to device
        $assignRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson("/api/v1/policies/{$policyId}/assign", [
                'device_id' => $device->id
            ]);

        $assignRes->assertStatus(200)
            ->assertJson(['message' => 'Policy assigned successfully.']);

        // Verify assignment in DB
        $this->assertDatabaseHas('policy_assignments', [
            'policy_id' => $policyId,
            'device_id' => $device->id
        ]);

        // 4. Device fetches its active policy
        $devicePolicyRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->getJson('/api/v1/device/policy');

        $devicePolicyRes->assertStatus(200)
            ->assertJsonFragment(['id' => $policyId, 'name' => 'Strict Security Config']);

        // 5. Device submits compliance status
        $statusRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->postJson('/api/v1/device/policy/status', [
                'policy_id' => $policyId,
                'status' => 'compliant',
                'details' => ['enforced_features' => ['camera_lock']]
            ]);

        $statusRes->assertStatus(200)
            ->assertJson(['message' => 'Policy status reported successfully.']);

        $this->assertDatabaseHas('device_policy_statuses', [
            'device_id' => $device->id,
            'policy_id' => $policyId,
            'status' => 'compliant'
        ]);
    }

    /**
     * Test admin audit logs retrieval.
     */
    public function test_audit_logs_retrieval(): void
    {
        $org = Organization::create(['name' => 'Alpha Org']);
        
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@alpha.com',
            'password' => 'secret123'
        ]);
        $admin->organizations()->attach($org->id);

        $adminToken = $this->jwtService->encode([
            'sub' => $admin->id,
            'type' => 'user'
        ]);

        // Insert a mock audit log record for this tenant
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'action' => 'policy_created',
            'auditable_type' => 'Policy',
            'auditable_id' => 99,
            'new_values' => ['name' => 'Strict Policy'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit'
        ]);

        // Admin pulls logs
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'action' => 'policy_created',
                'auditable_type' => 'Policy'
            ]);
    }
}
