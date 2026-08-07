<?php

namespace Modules\Commands\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;
use Modules\Devices\Domain\Entities\Device;

class Command extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'device_id',
        'command',
        'payload',
        'status',
        'result',
        'sent_at',
        'acknowledged_at',
        'completed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the device targeted by this command.
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
