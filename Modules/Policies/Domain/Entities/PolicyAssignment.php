<?php

namespace Modules\Policies\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class PolicyAssignment extends Model
{
    use BelongsToTenant;

    protected $table = 'policy_assignments';

    protected $fillable = [
        'organization_id',
        'policy_id',
        'device_id',
        'enrollment_profile_id'
    ];

    /**
     * Get the policy assigned.
     */
    public function policy()
    {
        return $this->belongsTo(Policy::class);
    }
}
