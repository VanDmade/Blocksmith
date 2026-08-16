<?php

namespace VanDmade\Blocksmith\Console\Commands;

use Throwable;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Services\AnchorBatchService;
use VanDmade\Blocksmith\Services\DocumentService;
use VanDmade\Blocksmith\Verification\DocumentIntegrityVerifier;

class VerifyDocumentCommand extends BlocksmithCommand
{

    protected $signature = 'blocksmith:verify-document {uuid} {--full} {--diagnose}';
    protected $description = 'Verify a document.';

    public function handle(
        AnchorBatchService $anchorBatchService,
        DocumentService $documentService
    ): int {
        try {
            return $this->verifyDocument($anchorBatchService, $documentService);
        } catch (Throwable $error) {
            BlocksmithLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            $this->error('An unexpected error occurred: '.$error->getMessage());
            return 1;
        }
    }

    private function verifyDocument(
        AnchorBatchService $anchorBatchService,
        DocumentService $documentService
    ): int {
        $uuid = $this->argument('uuid');
        $diagnose = $this->option('diagnose');
        // Diagnosing only makes sense against the whole history - there's nothing to
        // diagnose about checking a single revision on its own.
        $full = $this->option('full') || $diagnose;
        $document = $documentService->findByUuid($uuid);
        if (empty($document)) {
            $this->error('Document with UUID '.$uuid.' not found.');
            return 1;
        }
        $revisions = $documentService->getRevisionHistory($document);
        $counter = 0;
        $tamperedRevisions = [];
        $this->line('Verifying document '.$document->name.'.');
        // Definitely will run once
        do {
            $revision = $revisions[$counter];
            $this->line('Verifying document revision '.$revision->revision_number.'.');
            $failureMessage = $this->checkRevision($revision, $anchorBatchService, $document);
            if ($failureMessage !== null) {
                $this->error($failureMessage);
                if (!$diagnose) {
                    return 1;
                }
                // Diagnosing: note it and keep going, so a tamper that cascades down the
                // chain shows every affected revision instead of just the first one hit.
                $tamperedRevisions[] = $revision->revision_number;
            }
        } while (++$counter < count($revisions) && $full);
        if ($diagnose) {
            if (empty($tamperedRevisions)) {
                $this->line('Document '.$document->name.' verified successfully - no tampering found.');
                return 0;
            }
            $this->error(
                'Document '.$document->name.' has '.count($tamperedRevisions).' tampered revision(s): '.
                implode(', ', $tamperedRevisions).'.'
            );
            return 1;
        }
        $this->line('Document '.$document->name.' verified successfully.');
        return 0;
    }

    /**
     * Runs every check for a single revision and returns the failure message for the
     * first one that fails, or null if the revision is clean.
     */
    private function checkRevision(
        Revision $revision,
        AnchorBatchService $anchorBatchService,
        Document $document
    ): ?string {
        $results = app(DocumentIntegrityVerifier::class)->verify($revision);
        if ($results->checkFailed('chain') || $results->checkFailed('merkle_root') || $results->checkFailed('signature')) {
            foreach ($results->getErrors() as $error) {
                $this->line('Document '.$revision->revision_number.': '.$error);
            }
            return 'Document '.$revision->revision_number.' of document '.$document->name.' failed verification.';
        }
        // This config file option is allowed to be set that the verifier DOES call it, so if it's set to false we will check it
        if (!config('blocksmith.verify_content', true) && !$revision->pivot->verifyContent()) {
            return 'Content hash does not match actual content for revision '.$revision->revision_number.' of document '.$document->name;
        }
        if (empty($revision->anchorBatch)) {
            return 'Revision '.$revision->revision_number.' of document '.$document->name.' has no anchor batch.';
        }
        // Checks the merkle root within the anchor provider
        $anchorResponse = app(AnchorProviderInterface::class)->check($revision->anchorBatch->merkle_root);
        if (empty($anchorResponse)) {
            return 'Merkle root '.$revision->anchorBatch->merkle_root.' for revision '.$revision->revision_number.' of document '.$document->name.' is not anchored.';
        } elseif ($revision->anchorBatch->status != AnchorBatchStatus::CONFIRMED) {
            // Confirms the anchor batch as it wasn't confirmed yet, but due to the response it is now!
            // This is just something that will save time in the anchor batch job.
            $anchorBatchService->confirm($revision->anchorBatch, $anchorResponse);
        }
        return null;
    }

}
