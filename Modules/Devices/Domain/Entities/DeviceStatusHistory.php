<?php

namespace Modules\Devices\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Domain\Entities\User;

class DeviceStatusHistory extends Model
{
    protected $table = 'device_status_history';

    // Append-only history, so disable updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'device_id',
        'status',
        'reason',
        'changed_by_user_id'
    ];

    /**
     * Get the device.
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the user who changed the device status.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
