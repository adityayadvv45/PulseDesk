<?php

namespace App\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query to the current tenant.
 * The org id comes from TenantContext (the auth session), so cross-tenant
 * reads are impossible even if a client passes a foreign id.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $orgId = TenantContext::id();

        if ($orgId !== null) {
            $builder->where($model->getTable() . '.organization_id', $orgId);
        }
    }
}
