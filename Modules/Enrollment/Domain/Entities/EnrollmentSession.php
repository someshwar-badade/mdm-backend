<?php

namespace Modules\Enrollment\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class EnrollmentSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'enrollment_token_id',
        'device_uid',
        'status',
        'device_metadata',
        'device_secret',
        'ip_address',
        'user_agent',
        'completed_at'
    ];

    protected $casts = [
        'device_metadata' => 'array',
        'completed_at' => 'datetime'
    ];

    /**
     * Get the token used for this session.
     */
    public function token()
    {
        return $this->belongsTo(EnrollmentToken::class, 'enrollment_token_id');
    }
}
