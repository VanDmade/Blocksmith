<?php

namespace VanDmade\Blocksmith\Models\Documents;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Storage;
use VanDmade\Blocksmith\Models\Revision as BlocksmithRevision;
use VanDmade\Blocksmith\Services\HasherService;

class Revision extends Pivot
{

    protected $table = 'blocksmith_document_revisions';
    
    public $incrementing = true;

    protected $fillable = [
        'blocksmith_document_id',
        'blocksmith_revision_id',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Document>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'blocksmith_document_id');
    }

    /**
     * @return BelongsTo<BlocksmithRevision>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(BlocksmithRevision::class, 'blocksmith_revision_id');
    }

    /**
     * Confirms the file actually sitting at disk/path still matches what the
     * revision's content_hash claims
     */
    public function verifyContent(): bool
    {
        $currentHash = app(HasherService::class)->hashContent(
            Storage::disk($this->disk)->readStream($this->path)
        );
        return hash_equals($currentHash, $this->revision->content_hash);
    }

}
