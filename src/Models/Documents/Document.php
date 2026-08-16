<?php

namespace VanDmade\Blocksmith\Models\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use VanDmade\Blocksmith\Concerns\HasOrganization;
use VanDmade\Blocksmith\Models\Revision as BlocksmithRevision;
use VanDmade\Blocksmith\Enums\DocumentStatus;

class Document extends Model
{

    use HasOrganization, SoftDeletes;

    protected $table = 'blocksmith_documents';

    protected $fillable = [
        'blocksmith_document_type_id',
        'current_blocksmith_revision_id',
        'organization_id',
        'name',
        'uuid',
        'status',
        'description',
        'keywords',
        'metadata',
        'last_edited_at',
        'last_edited_by',
    ];

    protected $casts = [
        'status' => DocumentStatus::class,
        'metadata' => 'array',
        'keywords' => 'array',
        'last_edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'name' => 'Untitled Document',
        'status' => DocumentStatus::DRAFT,
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function($document) {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
            $document->created_by = Auth::check() ? Auth::id() : null;
        });
        static::deleting(function($document) {
            $document->deleted_by = Auth::check() ? Auth::id() : null;
            $document->save();
        });
    }

    /**
     * @return BelongsToMany<Type>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'blocksmith_document_type_id');
    }

    /**
     * All revisions linked to this document, via the blocksmith_document_revisions
     * pivot - blocksmith_revisions itself has no idea a Document exists at all.
     *
     * @return BelongsToMany<BlocksmithRevision>
     */
    public function revisions(): BelongsToMany
    {
        return $this->belongsToMany(
            BlocksmithRevision::class,
            'blocksmith_document_revisions',
            'blocksmith_document_id',
            'blocksmith_revision_id'
        )->using(Revision::class)
            ->withPivot(['disk', 'path', 'extension', 'mime_type', 'size', 'metadata'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<BlocksmithRevision>
     */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(BlocksmithRevision::class, 'current_blocksmith_revision_id');
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

    /**
     * @return BelongsTo<User>
     */
    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'last_edited_by');
    }

}
