<?php

namespace Modules\Policies\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class Policy extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'version',
        'settings'
    ];

    protected $casts = [
        'settings' => 'array',
        'version' => 'integer'
    ];

    /**
     * Get assignments associated with this policy.
     */
    public function assignments()
    {
        return $this->hasMany(PolicyAssignment::class, 'policy_id');
    }

    /**
     * Get device compliance statuses associated with this policy.
     */
    public function deviceStatuses()
    {
        return $this->hasMany(DevicePolicyStatus::class, 'policy_id');
    }
}
