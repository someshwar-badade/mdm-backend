<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Enrollment\Domain\Entities\EnrollmentProfile;
use Modules\Enrollment\Domain\Entities\EnrollmentToken;
use Modules\Enrollment\Domain\Entities\EnrollmentSession;
use Modules\Devices\Domain\Entities\Device;
use Modules\Devices\Domain\Entities\DeviceIdentity;
use Modules\Devices\Domain\Entities\DeviceStatusHistory;
use Modules\Devices\Domain\Entities\DeviceEvent;
use Modules\Identity\Application\Services\JwtService;
use Modules\Tenancy\Application\Services\TenantContext;

class EnrollmentFlowTest extends TestCase
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
     * Test the Admin Portal APIs: Creating Profile, Listing Profiles, fetching Provisioning QR.
     */
    public function test_admin_enrollment_profile_apis(): void
    {
        // 1. Setup Organization, User, and authentication token
        $org = Organization::create(['name' => 'Brick Org']);
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@brick.com',
            'password' => 'secret123'
        ]);
        $user->organizations()->attach($org->id);

        $adminToken = $this->jwtService->encode([
            'sub' => $user->id,
            'type' => 'user'
        ]);

        // 2. POST /api/v1/enrollment-profiles (Create profile)
        $profileData = [
            'name' => 'Standard Android Kiosk',
            'description' => 'Profile for warehouse scanning devices.',
            'wifi_ssid' => 'Brick-Warehouse-WiFi',
            'wifi_password' => 'warehousepw123',
            'wifi_security_type' => 'WPA',
            'android_package_name' => 'com.brick.mdm',
            'android_download_url' => 'https://mdm.brick.com/downloads/agent-v2.apk',
            'android_checksum' => 'checksum_hash_abc_123',
            'android_extras' => ['environment' => 'production'],
            'is_default' => true
        ];

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->postJson('/api/v1/enrollment-profiles', $profileData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Standard Android Kiosk',
                'wifi_ssid' => 'Brick-Warehouse-WiFi'
            ])
            ->assertJsonStructure([
                'profile' => ['id', 'organization_id', 'name'],
                'default_token'
            ]);

        $profileId = $response->json('profile.id');
        $enrollToken = $response->json('default_token');

        // Verify organization_id is applied to profile and token
        $this->assertEquals($org->id, $response->json('profile.organization_id'));
        $tokenRecord = EnrollmentToken::where('token', $enrollToken)->first();
        $this->assertNotNull($tokenRecord);
        $this->assertEquals($org->id, $tokenRecord->organization_id);

        // 3. GET /api/v1/enrollment-profiles (List profiles)
        $listResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->getJson('/api/v1/enrollment-profiles');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Standard Android Kiosk']);

        // 4. GET /api/v1/enrollment-profiles/{id}/qr (QR Provisioning Payload)
        $qrResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->withHeader('X-Organization-Id', (string) $org->id)
            ->getJson("/api/v1/enrollment-profiles/{$profileId}/qr");

        $qrResponse->assertStatus(200)
            ->assertJson([
                'android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME' => 'com.brick.mdm/com.brick.mdm.receiver.DeviceAdminReceiver',
                'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_URL' => 'https://mdm.brick.com/downloads/agent-v2.apk',
                'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_CHECKSUM' => 'checksum_hash_abc_123',
                'android.app.extra.PROVISIONING_WIFI_SSID' => 'Brick-Warehouse-WiFi',
                'android.app.extra.PROVISIONING_WIFI_PASSWORD' => 'warehousepw123',
                'android.app.extra.PROVISIONING_WIFI_SECURITY_TYPE' => 'WPA',
                'android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE' => [
                    'environment' => 'production',
                    'enrollment_token' => $enrollToken
                ]
            ]);
    }

    /**
     * Test the Device Agent Onboarding: Registering and Activating a device step-by-step.
     */
    public function test_device_agent_onboarding_flow(): void
    {
        $org = Organization::create(['name' => 'Brick Org']);
        $profile = EnrollmentProfile::create([
            'organization_id' => $org->id,
            'name' => 'Warehouse Profile',
            'android_package_name' => 'com.brick.mdm'
        ]);

        $token = EnrollmentToken::create([
            'organization_id' => $org->id,
            'enrollment_profile_id' => $profile->id,
            'token' => 'enroll_token_secret',
            'expires_at' => now()->addHour(),
            'max_uses' => 5,
            'uses_count' => 0,
            'status' => 'active'
        ]);

        // 1. POST /api/v1/device-enrollment/register (Device Agent Initiating registration)
        $registerData = [
            'enrollment_token' => 'enroll_token_secret',
            'serial_number' => 'SR_XYZ_98765',
            'hardware_manufacturer' => 'Honeywell',
            'hardware_model' => 'CT40',
            'os_version' => 'Android 11.0',
            'sdk_version' => 30,
            'imei' => '359876543210987',
            'mac_address' => '00:11:22:33:44:55'
        ];

        $registerRes = $this->postJson('/api/v1/device-enrollment/register', $registerData);

        $registerRes->assertStatus(201)
            ->assertJsonStructure([
                'enrollment_session_id',
                'device_uid',
                'device_secret'
            ]);

        $sessionId = $registerRes->json('enrollment_session_id');
        $deviceUid = $registerRes->json('device_uid');
        $tempSecret = $registerRes->json('device_secret');

        // Confirm enrollment session is stored correctly
        $session = EnrollmentSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals('initiated', $session->status);
        $this->assertEquals($org->id, $session->organization_id);

        // Confirm token uses_count incremented
        $this->assertEquals(1, $token->fresh()->uses_count);

        // 2. POST /api/v1/device-enrollment/activate (Device Agent completing enrollment)
        $activateData = [
            'enrollment_session_id' => $sessionId,
            'device_uid' => $deviceUid
        ];

        $activateRes = $this->postJson('/api/v1/device-enrollment/activate', $activateData);

        $activateRes->assertStatus(200)
            ->assertJsonStructure([
                'device_uid',
                'device_secret',
                'access_token',
                'expires_in'
            ]);

        $accessToken = $activateRes->json('access_token');

        // Confirm session completed
        $this->assertEquals('completed', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->completed_at);

        // Verify Device record created
        $device = Device::where('device_uid', $deviceUid)->first();
        $this->assertNotNull($device);
        $this->assertEquals('active', $device->status);
        $this->assertEquals($org->id, $device->organization_id);
        $this->assertEquals($profile->id, $device->enrollment_profile_id);

        // Verify Device Identity record created
        $identity = DeviceIdentity::where('device_id', $device->id)->first();
        $this->assertNotNull($identity);
        $this->assertEquals('SR_XYZ_98765', $identity->serial_number);
        $this->assertEquals('Honeywell', $identity->hardware_manufacturer);
        $this->assertEquals('CT40', $identity->hardware_model);

        // Verify Status History created
        $history = DeviceStatusHistory::where('device_id', $device->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals('active', $history->status);

        // Verify Device Event created
        $event = DeviceEvent::where('device_id', $device->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('enrollment', $event->event_type);

        // 3. Request tenant-only endpoint with returned device access token
        // Verify it resolves the tenant and works correctly
        $tenantResponse = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/v1/tenancy/tenant-only');

        $tenantResponse->assertStatus(200)
            ->assertJson(['organization_id' => $org->id]);
    }
}
