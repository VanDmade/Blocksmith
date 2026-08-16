<?php

namespace VanDmade\Blocksmith\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VanDmade\Blocksmith\Events\DocumentUploaded;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Documents\Revision;
use VanDmade\Blocksmith\Models\SigningKey;

class DocumentService
{

    private const SORTABLE_COLUMNS = ['id', 'name', 'status', 'created_at', 'updated_at'];
    private const SEARCHABLE_COLUMNS = ['name', 'description'];

    public function __construct(
        private readonly RevisionService $revisionService,
    ) {
    }

    public function find(int $id): ?Document
    {
        return Document::find($id);
    }

    public function findByUuid(string $uuid): ?Document
    {
        return Document::where('uuid', $uuid)->first();
    }

    public function search(array $options, string $defaultSort = 'created_at'): Builder
    {
        $defaultOrder = 'asc';
        $validSortBy = in_array($options['sort_by'] ?? null, self::SORTABLE_COLUMNS, true);
        $sortBy = $validSortBy ? $options['sort_by'] : $defaultSort;
        $sortOrder = ($options['sort_order'] ?? $defaultOrder) === 'desc' ? 'desc' : 'asc';
        return Document::select($options['select'] ?? ['*'])
            ->when(!empty($options['search']), function($query) use ($options) {
                $search = $options['search'];
                $query->where(function($query) use ($search) {
                    foreach (self::SEARCHABLE_COLUMNS as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy($sortBy, $sortOrder);
    }

    public function create(
        array $documentAttributes,
        string $contentHash,
        array $pivotAttributes,
        ?SigningKey $signingKey = null,
    ): Document {
        $document = Document::create($documentAttributes);
        return $this->revise($document, $contentHash, $pivotAttributes, $signingKey);
    }

    public function revise(
        Document $document,
        string $contentHash,
        array $pivotAttributes,
        ?SigningKey $signingKey = null,
        ?string $revisionReason = null,
    ): Document {
        $revision = null;
        $document = DB::transaction(function() use (
            $document,
            $contentHash,
            $pivotAttributes,
            $signingKey,
            $revisionReason,
            &$revision
        ) {
            $revision = $this->revisionService->create(
                $document->currentRevision?->hash,
                $contentHash,
                ($document->currentRevision?->revision_number ?? 0) + 1,
                $signingKey,
                $revisionReason
            );
            // Updates the identities for the pivot table
            $pivotAttributes['blocksmith_document_id'] = $document->id;
            $pivotAttributes['blocksmith_revision_id'] = $revision->id;
            Revision::create($pivotAttributes);
            // Adds in the revision
            $document->current_blocksmith_revision_id = $revision->id;
            $document->save();
            return $document->fresh();
        });
        // Dispatched after the transaction commits, not inside it.
        DocumentUploaded::dispatch($document, $revision);
        return $document;
    }

    /**
     * Updates the document's own fields only - name/description/keywords/metadata.
     * Not a new revision, doesn't touch content/hash/anchoring at all.
     */
    public function update(Document $document, array $attributes): Document
    {
        $document->fill($attributes);
        $document->save();
        return $document;
    }

    public function delete(Document|int $document): bool
    {
        return $this->resolveDocument($document)->delete();
    }

    public function getCurrentRevision(Document|int $document): ?Revision
    {
        return $this->resolveDocument($document)->currentRevision;
    }

    public function getRevisionHistory(Document|int $document): Collection
    {
        return $this->resolveDocument($document)->revisions()
            ->withTrashed()
            ->orderBy('revision_number')
            ->get();
    }

    private function resolveDocument(Document|int $document): Document
    {
        return is_int($document) ? Document::findOrFail($document) : $document;
    }

}
