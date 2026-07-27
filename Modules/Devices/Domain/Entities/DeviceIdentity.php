<?php

namespace Modules\Devices\Domain\Entities;

use Illuminate\Database\Eloquent\Model;

class DeviceIdentity extends Model
{
    protected $table = 'device_identities';

    protected $fillable = [
        'device_id',
        'serial_number',
        'imei',
        'mac_address',
        'hardware_manufacturer',
        'hardware_model',
        'os_version',
        'sdk_version'
    ];

    /**
     * Get the device associated with this identity record.
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
