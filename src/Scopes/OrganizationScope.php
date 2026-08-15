<?php

namespace VanDmade\Blocksmith\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{

    public function apply(Builder $builder, Model $model): void
    {
        if (is_null(config('blocksmith.organization_model'))) {
            return;
        }
        $organizationId = auth()->user()?->organization_id ?? null;
        if (is_null($organizationId)) {
            return;
        }
        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }

}
