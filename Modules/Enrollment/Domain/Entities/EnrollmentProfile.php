<?php

namespace Modules\Enrollment\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class EnrollmentProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'wifi_ssid',
        'wifi_password',
        'wifi_security_type',
        'android_package_name',
        'android_checksum',
        'android_download_url',
        'android_extras',
        'is_default'
    ];

    protected $casts = [
        'android_extras' => 'array',
        'is_default' => 'boolean'
    ];

    /**
     * Generate the Android Enterprise QR provisioning payload.
     */
    public function getProvisioningPayload(string $serverUrl, string $tokenString): array
    {
        $payload = [
            'android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME' => $this->android_package_name . '/com.brick.mdm.receiver.DeviceAdminReceiver',
            'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_URL' => $this->android_download_url ?? "{$serverUrl}/downloads/agent.apk",
        ];

        if ($this->android_checksum) {
            $payload['android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_CHECKSUM'] = $this->android_checksum;
        }

        if ($this->wifi_ssid) {
            $payload['android.app.extra.PROVISIONING_WIFI_SSID'] = $this->wifi_ssid;
            if ($this->wifi_password) {
                $payload['android.app.extra.PROVISIONING_WIFI_PASSWORD'] = $this->wifi_password;
            }
            if ($this->wifi_security_type) {
                $payload['android.app.extra.PROVISIONING_WIFI_SECURITY_TYPE'] = $this->wifi_security_type;
            }
        }

        $payload['android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE'] = array_merge($this->android_extras ?? [], [
            'server_url' => $serverUrl,
            'enrollment_token' => $tokenString
        ]);

        return $payload;
    }
}
