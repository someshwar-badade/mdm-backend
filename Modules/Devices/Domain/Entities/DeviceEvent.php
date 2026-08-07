<?php

namespace Modules\Devices\Domain\Entities;

use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    protected $table = 'device_events';

    // Append-only logs, so disable updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'device_id',
        'event_type',
        'severity',
        'payload'
    ];

    protected $casts = [
        'payload' => 'array'
    ];

    /**
     * Get the device.
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
