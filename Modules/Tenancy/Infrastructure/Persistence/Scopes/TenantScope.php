<?php

namespace Modules\Tenancy\Infrastructure\Persistence\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Tenancy\Application\Services\TenantContext;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::has()) {
            $builder->where($model->getTable() . '.organization_id', TenantContext::get());
        }
    }
}
