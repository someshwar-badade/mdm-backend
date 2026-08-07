<?php

namespace Modules\Policies\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class DevicePolicyStatus extends Model
{
    use BelongsToTenant;

    protected $table = 'device_policy_statuses';

    protected $fillable = [
        'organization_id',
        'device_id',
        'policy_id',
        'status',
        'details'
    ];

    protected $casts = [
        'details' => 'array'
    ];

    /**
     * Get the policy.
     */
    public function policy()
    {
        return $this->belongsTo(Policy::class);
    }
}
