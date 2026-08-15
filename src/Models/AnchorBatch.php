<?php

namespace VanDmade\Blocksmith\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;

class AnchorBatch extends Model
{

    protected $table = 'blocksmith_anchor_batches';

    protected $fillable = [
        'submitted_at',
        'confirmed_at',
        'failed_at',
        'merkle_root',
        'status',
        'provider',
        'proof_reference',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'status' => AnchorBatchStatus::class,
    ];

    protected $attributes = [
        'status' => AnchorBatchStatus::PENDING,
    ];

    public static function booted(): void
    {
        static::creating(function (AnchorBatch $anchorBatch) {
            if (is_null($anchorBatch->provider)) {
                // Gets the provider from the configured anchor provider class
                $anchorBatch->provider = app(AnchorProviderInterface::class)->getProvider();
            }
        });
    }

    /**
     * @return HasMany<Revision>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'blocksmith_anchor_batch_id');
    }

}
