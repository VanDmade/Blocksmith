<?php

namespace VanDmade\Blocksmith\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VanDmade\Blocksmith\Events\DocumentUploaded;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Documents\Revision;
use VanDmade\Blocksmith\Models\SigningKey;

class DocumentService
{

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
