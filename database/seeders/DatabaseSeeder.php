<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Enrollment\Domain\Entities\EnrollmentProfile;
use Modules\Enrollment\Domain\Entities\EnrollmentToken;
use Modules\Devices\Domain\Entities\Device;
use Modules\Devices\Domain\Entities\DeviceIdentity;
use Modules\Devices\Domain\Entities\DeviceStatusHistory;
use Modules\Devices\Domain\Entities\DeviceEvent;
use Modules\Tenancy\Application\Services\TenantContext;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Clear Tenant Context during seeding to prevent query scope intercepts
        TenantContext::clear();

        // 1. Create Organizations
        $orgBrick = Organization::create(['name' => 'Brick Corp']);
        $orgAcme = Organization::create(['name' => 'Acme Logistics']);

        // 2. Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mdm.com',
            'password' => 'secret123', // Automatically hashed via cast
        ]);

        // Link Admin to both organizations
        $admin->organizations()->attach([$orgBrick->id, $orgAcme->id]);

        // 3. Create Seed Data for Organization 1: Brick Corp
        $profileBrick = EnrollmentProfile::create([
            'organization_id' => $orgBrick->id,
            'name' => 'Standard Warehouse Kiosk',
            'description' => 'Profile configuration for warehouse scanning terminals.',
            'wifi_ssid' => 'Brick-Warehouse-WiFi',
            'wifi_password' => 'warehousepw123',
            'wifi_security_type' => 'WPA',
            'android_package_name' => 'com.brick.mdm',
            'android_download_url' => 'https://mdm.brick.com/downloads/agent-v2.apk',
            'android_checksum' => 'checksum_hash_abc_123',
            'android_extras' => ['environment' => 'production'],
            'is_default' => true
        ]);

        EnrollmentToken::create([
            'organization_id' => $orgBrick->id,
            'enrollment_profile_id' => $profileBrick->id,
            'token' => 'token_brick12345',
            'expires_at' => now()->addYear(),
            'max_uses' => 1000,
            'uses_count' => 2,
            'status' => 'active'
        ]);

        // Create Demo Device 1: Honeywell CT40
        $device1 = Device::create([
            'organization_id' => $orgBrick->id,
            'enrollment_profile_id' => $profileBrick->id,
            'device_uid' => 'dev_honeywell123',
            'name' => 'Honeywell CT40 Scanner',
            'device_secret' => bcrypt('device-secret-1'),
            'status' => 'active'
        ]);

        DeviceIdentity::create([
            'device_id' => $device1->id,
            'serial_number' => 'SR_HW_88192',
            'imei' => '359876543210987',
            'mac_address' => '00:11:22:33:44:55',
            'hardware_manufacturer' => 'Honeywell',
            'hardware_model' => 'CT40',
            'os_version' => 'Android 11.0',
            'sdk_version' => 30
        ]);

        DeviceStatusHistory::create([
            'device_id' => $device1->id,
            'status' => 'active',
            'reason' => 'Device activated via seeder.'
        ]);

        DeviceEvent::create([
            'device_id' => $device1->id,
            'event_type' => 'enrollment',
            'severity' => 'info',
            'payload' => ['seeder' => true]
        ]);

        // Create Demo Device 2: Samsung Galaxy Tab
        $device2 = Device::create([
            'organization_id' => $orgBrick->id,
            'enrollment_profile_id' => $profileBrick->id,
            'device_uid' => 'dev_samsung456',
            'name' => 'Samsung Galaxy Tab Active4',
            'device_secret' => bcrypt('device-secret-2'),
            'status' => 'active'
        ]);

        DeviceIdentity::create([
            'device_id' => $device2->id,
            'serial_number' => 'SR_SS_99812',
            'imei' => '354456789012345',
            'mac_address' => '66:77:88:99:AA:BB',
            'hardware_manufacturer' => 'Samsung',
            'hardware_model' => 'Galaxy Tab Active4',
            'os_version' => 'Android 12.0',
            'sdk_version' => 31
        ]);

        DeviceStatusHistory::create([
            'device_id' => $device2->id,
            'status' => 'active',
            'reason' => 'Device activated via seeder.'
        ]);

        DeviceEvent::create([
            'device_id' => $device2->id,
            'event_type' => 'enrollment',
            'severity' => 'info',
            'payload' => ['seeder' => true]
        ]);

        // 4. Create Seed Data for Organization 2: Acme Logistics
        $profileAcme = EnrollmentProfile::create([
            'organization_id' => $orgAcme->id,
            'name' => 'Acme Delivery Terminal',
            'description' => 'Profile configuration for fleet logistics and routing devices.',
            'wifi_ssid' => 'Acme-Fleet-WiFi',
            'wifi_password' => 'fleetpw789',
            'wifi_security_type' => 'WPA',
            'android_package_name' => 'com.brick.mdm',
            'android_download_url' => 'https://mdm.brick.com/downloads/agent-v2.apk',
            'android_checksum' => 'checksum_hash_abc_123',
            'android_extras' => ['environment' => 'production'],
            'is_default' => true
        ]);

        EnrollmentToken::create([
            'organization_id' => $orgAcme->id,
            'enrollment_profile_id' => $profileAcme->id,
            'token' => 'token_acme12345',
            'expires_at' => now()->addYear(),
            'max_uses' => 1000,
            'uses_count' => 0,
            'status' => 'active'
        ]);
    }
}
