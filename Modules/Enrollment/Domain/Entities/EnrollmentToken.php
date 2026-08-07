<?php

namespace Modules\Enrollment\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class EnrollmentToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'enrollment_profile_id',
        'token',
        'expires_at',
        'max_uses',
        'uses_count',
        'status'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
    ];

    /**
     * Get the enrollment profile associated with this token.
     */
    public function profile()
    {
        return $this->belongsTo(EnrollmentProfile::class, 'enrollment_profile_id');
    }

    /**
     * Check if the token is valid for enrollment.
     */
    public function isValid(): bool
    {
        return $this->status === 'active' 
            && $this->expires_at->isFuture() 
            && $this->uses_count < $this->max_uses;
    }
}
