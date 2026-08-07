<?php

namespace Modules\Organizations\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Domain\Entities\User;

class Organization extends Model
{
    protected $fillable = ['name', 'subdomain', 'domain', 'status'];

    /**
     * Get the users belonging to the organization.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_users', 'organization_id', 'user_id')
                    ->withTimestamps();
    }
}
