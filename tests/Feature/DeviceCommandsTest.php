<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Devices\Domain\Entities\Device;
use Modules\Devices\Domain\Entities\DeviceIdentity;
use Modules\Commands\Domain\Entities\Command;
use Modules\Identity\Application\Services\JwtService;
use Modules\Tenancy\Application\Services\TenantContext;

class DeviceCommandsTest extends TestCase
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
     * Test device heartbeat and inventory endpoints.
     */
    public function test_device_heartbeat_and_inventory_endpoints(): void
    {
        $org = Organization::create(['name' => 'Brick Org']);
        
        $device = Device::create([
            'organization_id' => $org->id,
            'device_uid' => 'dev_agent_pixel_123',
            'name' => 'Pixel 8',
            'status' => 'active',
            'device_secret' => bcrypt('pixelpwd123')
        ]);

        $deviceToken = $this->jwtService->encode([
            'sub' => $device->device_uid,
            'type' => 'device',
            'org' => $org->id
        ]);

        // 1. Send Heartbeat
        $heartbeatPayload = [
            'battery' => ['level' => 82, 'charging' => true],
            'network' => ['type' => 'wifi', 'connected' => true],
            'storage' => ['totalBytes' => 128000000000, 'availableBytes' => 83000000000],
            'agent' => ['version' => '1.0.0', 'policyVersion' => 4]
        ];

        $heartbeatRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->postJson("/api/v1/device/heartbeat", $heartbeatPayload);

        $heartbeatRes->assertStatus(200)
            ->assertJson(['message' => 'Heartbeat acknowledged.']);

        $this->assertNotNull($device->fresh()->last_heartbeat_at);

        // 2. Put Inventory details
        $inventoryPayload = [
            'serial_number' => 'SR_PIXEL_8892',
            'imei' => '359876123456789',
            'mac_address' => 'AB:CD:EF:12:34:56',
            'hardware_manufacturer' => 'Google',
            'hardware_model' => 'Pixel 8',
            'os_version' => 'Android 14',
            'sdk_version' => 34
        ];

        $inventoryRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->putJson("/api/v1/device/inventory", $inventoryPayload);

        $inventoryRes->assertStatus(200)
            ->assertJson(['message' => 'Device inventory updated successfully.']);

        $identity = DeviceIdentity::where('device_id', $device->id)->first();
        $this->assertNotNull($identity);
        $this->assertEquals('SR_PIXEL_8892', $identity->serial_number);
    }

    /**
     * Test complete commands flow: admin queueing, device fetching, acknowledging, and completing.
     */
    public function test_complete_mdm_commands_lifecycle(): void
    {
        $org = Organization::create(['name' => 'Acme Corp']);
        
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@acme.com',
            'password' => 'secret123'
        ]);
        $adminUser->organizations()->attach($org->id);

        $adminToken = $this->jwtService->encode([
            'sub' => $adminUser->id,
            'type' => 'user'
        ]);

        $device = Device::create([
            'organization_id' => $org->id,
            'device_uid' => 'dev_logistics_123',
            'name' => 'Acme Scanner',
            'status' => 'active',
            'device_secret' => bcrypt('pwd123')
        ]);

        $deviceToken = $this->jwtService->encode([
            'sub' => $device->device_uid,
            'type' => 'device',
            'org' => $org->id
        ]);

        // 1. Admin queues command: remote lock
        $queueRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson("/api/v1/devices/{$device->id}/commands", [
                'command' => 'lock',
                'payload' => 'Force locking screen for test.'
            ]);

        $queueRes->assertStatus(201)
            ->assertJsonStructure(['command_id', 'message']);

        $commandId = $queueRes->json('command_id');

        // Confirm command stored in DB
        $command = Command::find($commandId);
        $this->assertNotNull($command);
        $this->assertEquals('pending', $command->status);

        // 2. Admin retrieves commands queue for the device
        $listRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->getJson("/api/v1/devices/{$device->id}/commands");

        $listRes->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['command' => 'lock']);

        // 3. Device Agent queries pending commands
        $pendingRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->getJson('/api/v1/device/commands/pending');

        $pendingRes->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $commandId, 'command' => 'lock']);

        // 4. Device Agent acknowledges delivery of the command
        $ackRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->postJson("/api/v1/device/commands/{$commandId}/acknowledge");

        $ackRes->assertStatus(200)
            ->assertJson(['message' => 'Command delivery acknowledged.']);

        $this->assertEquals('acknowledged', $command->fresh()->status);
        $this->assertNotNull($command->fresh()->acknowledged_at);

        // 5. Device Agent completes execution and reports result
        $resultRes = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->postJson("/api/v1/device/commands/{$commandId}/result", [
                'status' => 'success',
                'result' => ['details' => 'Device locked successfully.']
            ]);

        $resultRes->assertStatus(200)
            ->assertJson(['message' => 'Command outcome reported successfully.']);

        $completedCommand = $command->fresh();
        $this->assertEquals('success', $completedCommand->status);
        $this->assertNotNull($completedCommand->completed_at);
        $this->assertEquals(['details' => 'Device locked successfully.'], $completedCommand->result);
    }

    /**
     * Test device registering and updating its FCM token.
     */
    public function test_device_fcm_token_registration(): void
    {
        $org = Organization::create(['name' => 'Brick Org']);
        
        $device = Device::create([
            'organization_id' => $org->id,
            'device_uid' => 'dev_fcm_123',
            'name' => 'FCM Pixel',
            'status' => 'active',
            'device_secret' => bcrypt('secret123')
        ]);

        $deviceToken = $this->jwtService->encode([
            'sub' => $device->device_uid,
            'type' => 'device',
            'org' => $org->id
        ]);

        $fcmPayload = [
            'fcm_token' => 'mock_fcm_token_string_abc_123'
        ];

        $response = $this->withHeader('Authorization', "Bearer {$deviceToken}")
            ->postJson("/api/v1/device/fcm-token", $fcmPayload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'FCM Token updated successfully.']);

        $this->assertEquals('mock_fcm_token_string_abc_123', $device->fresh()->fcm_token);
    }
}

