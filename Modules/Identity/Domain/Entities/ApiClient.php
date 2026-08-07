<?php

namespace Modules\Identity\Domain\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Tenancy\Domain\Traits\BelongsToTenant;

class ApiClient extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'name', 'client_id', 'client_secret', 'status'];

    protected $hidden = ['client_secret'];
}
