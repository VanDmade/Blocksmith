<?php

namespace VanDmade\Blocksmith\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VanDmade\Blocksmith\Concerns\HasOrganization;
use VanDmade\Blocksmith\Models\Documents\Document;

class Log extends Model
{

    use HasOrganization;

    const UPDATED_AT = null;

    protected $table = 'blocksmith_logs';

    protected $fillable = [
        'created_by',
        'blocksmith_document_id',
        'blocksmith_revision_id',
        'blocksmith_signing_key_id',
        'blocksmith_anchor_batch_id',
        'event',
        'level',
        'message',
        'file',
        'line',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $attributes = [
        'level' => 'info',
    ];

    /**
     * @return BelongsTo<Document>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'blocksmith_document_id');
    }

    /**
     * @return BelongsTo<Revision>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'blocksmith_revision_id');
    }

    /**
     * @return BelongsTo<SigningKey>
     */
    public function signingKey(): BelongsTo
    {
        return $this->belongsTo(SigningKey::class, 'blocksmith_signing_key_id');
    }

    /**
     * @return BelongsTo<AnchorBatch>
     */
    public function anchorBatch(): BelongsTo
    {
        return $this->belongsTo(AnchorBatch::class, 'blocksmith_anchor_batch_id');
    }

    /**
     * @return BelongsTo<User>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

}
