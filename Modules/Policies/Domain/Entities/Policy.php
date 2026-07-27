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
}
