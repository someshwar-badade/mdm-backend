<?php

namespace Modules\Tenancy\Domain\Traits;

use Modules\Tenancy\Application\Services\TenantContext;
use Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Modules\Organizations\Domain\Entities\Organization;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (!$model->organization_id && TenantContext::has()) {
                $model->organization_id = TenantContext::get();
            }
        });
    }

    /**
     * Get the organization that owns this entity.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
