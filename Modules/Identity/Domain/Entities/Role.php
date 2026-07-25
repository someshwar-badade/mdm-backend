<?php

namespace Modules\Identity\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'name', 'slug', 'description'];

    /**
     * The permissions associated with this role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    /**
     * The users assigned to this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
                    ->withPivot('organization_id')
                    ->withTimestamps();
    }
}
