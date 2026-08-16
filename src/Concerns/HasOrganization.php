<?php

namespace VanDmade\Blocksmith\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VanDmade\Blocksmith\Scopes\OrganizationScope;

trait HasOrganization
{

    public static function bootHasOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(config('blocksmith.organization_model'), 'organization_id');
    }

}
