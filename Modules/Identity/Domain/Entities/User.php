<?php

namespace Modules\Identity\Domain\Entities;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Organizations\Domain\Entities\Organization;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Get the organizations the user belongs to.
     */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_users', 'user_id', 'organization_id')
                    ->withTimestamps();
    }

    /**
     * Get the roles assigned to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
                    ->withPivot('organization_id')
                    ->withTimestamps();
    }
}
