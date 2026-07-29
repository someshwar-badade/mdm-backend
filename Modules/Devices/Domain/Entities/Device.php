<?php

namespace Modules\Devices\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;
use Modules\Enrollment\Domain\Entities\EnrollmentProfile;

class Device extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'enrollment_profile_id',
        'device_uid',
        'name',
        'status',
        'device_secret',
        'last_heartbeat_at',
        'fcm_token'
    ];

    protected $hidden = ['device_secret'];

    protected $casts = [
        'device_secret' => 'hashed',
        'last_heartbeat_at' => 'datetime'
    ];

    /**
     * Get the enrollment profile used by this device.
     */
    public function profile()
    {
        return $this->belongsTo(EnrollmentProfile::class, 'enrollment_profile_id');
    }

    /**
     * Get the hardware identity of the device.
     */
    public function identity()
    {
        return $this->hasOne(DeviceIdentity::class, 'device_id');
    }

    /**
     * Get the status changes history.
     */
    public function statusHistory()
    {
        return $this->hasMany(DeviceStatusHistory::class, 'device_id');
    }

    /**
     * Get the event log for this device.
     */
    public function events()
    {
        return $this->hasMany(DeviceEvent::class, 'device_id');
    }

    /**
     * Get the command queue for this device.
     */
    public function commands()
    {
        return $this->hasMany(\Modules\Commands\Domain\Entities\Command::class, 'device_id');
    }
}
