<?php

namespace Modules\Audit\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;
use Modules\Identity\Domain\Entities\User;

class AuditLog extends Model
{
    use BelongsToTenant;

    // Disable standard Eloquent updated_at field since audits are append-only.
    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
