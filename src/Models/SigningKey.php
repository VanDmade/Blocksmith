<?php

namespace VanDmade\Blocksmith\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SigningKey extends Model
{

    protected $table = 'blocksmith_signing_keys';

    protected $fillable = [
        'user_id',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
        'name',
        'public_key',
        'private_key',
        'custodial',
        'algorithm',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'custodial' => 'boolean',
        'private_key' => 'encrypted',
    ];

    protected $attributes = [
        'custodial' => false,
        'algorithm' => 'ed25519',
    ];

    protected $hidden = [
        'private_key',
    ];

    /**
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /**
     * @return BelongsTo<User>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'revoked_by');
    }

    /**
     * @return HasMany<Revision>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'blocksmith_signing_key_id');
    }

}
