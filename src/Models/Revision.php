<?php

namespace VanDmade\Blocksmith\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use VanDmade\Blocksmith\Enums\RevisionStatus;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Documents\Revision as DocumentRevision;

class Revision extends Model
{

    use SoftDeletes;

    protected $table = 'blocksmith_revisions';

    protected $fillable = [
        'blocksmith_signing_key_id',
        'blocksmith_anchor_batch_id',
        'uuid',
        'revision_number',
        'revision_identifier',
        'revision_reason',
        'status',
        'content_hash',
        'previous_hash',
        'signature',
        'hash',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'status' => RevisionStatus::class,
    ];

    protected $attributes = [
        'revision_number' => 1,
        'status' => RevisionStatus::PENDING,
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($revision) {
            if (empty($revision->uuid)) {
                $revision->uuid = (string) Str::uuid();
            }
            $revision->created_by = Auth::check() ? Auth::id() : null;
        });
        static::deleting(function ($revision) {
            $revision->deleted_by = Auth::check() ? Auth::id() : null;
            $revision->save();
        });
    }

    // Purely for convenience
    public function document(): ?Document
    {
        return $this->documents->first();
    }

    /**
     * @return BelongsToMany<Document>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'blocksmith_document_revisions',
            'blocksmith_revision_id',
            'blocksmith_document_id'
        )->using(DocumentRevision::class)
            ->withPivot(['disk', 'path', 'extension', 'mime_type', 'size', 'metadata'])
            ->withTimestamps();
    }

    /**
     * @return HasOne<RevisionProof>
     */
    public function proof(): HasOne
    {
        return $this->hasOne(RevisionProof::class, 'blocksmith_revision_id');
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

    /**
     * @return BelongsTo<User>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'deleted_by');
    }

}
