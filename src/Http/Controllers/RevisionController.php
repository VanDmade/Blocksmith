<?php

namespace VanDmade\Blocksmith\Http\Controllers;

use Illuminate\Http\JsonResponse;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Services\DocumentService;
use Exception;

class RevisionController extends BlocksmithController
{

    public function __construct(
        protected DocumentService $documentService,
    ) {
    }

    public function get(Revision $revision): JsonResponse
    {
        try {
            return $this->success([
                'revision' => $this->revisionDetails($revision),
            ]);
        } catch (Exception $error) {
            BlocksmithLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

    public function history(Document $document): JsonResponse
    {
        try {
            $revisions = $this->documentService->getRevisionHistory($document);
            return $this->success([
                'document' => $document->uuid,
                'revisions' => $revisions->map(fn(Revision $revision) => $this->revisionDetails($revision))->all(),
            ]);
        } catch (Exception $error) {
            BlocksmithLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

    private function revisionDetails(Revision $revision): array
    {
        return [
            'id' => $revision->id,
            'uuid' => $revision->uuid,
            'hash' => $revision->hash,
            'content_hash' => $revision->content_hash,
            'previous_hash' => $revision->previous_hash,
            'revision_number' => $revision->revision_number,
            'revision_identifier' => $revision->revision_identifier,
            'revision_reason' => $revision->revision_reason,
            'status' => $revision->status,
            'has_anchor_batch' => !empty($revision->blocksmith_anchor_batch_id),
            'has_signing_key' => !empty($revision->blocksmith_signing_key_id),
            'deleted_at' => $revision->deleted_at,
        ];
    }

}
