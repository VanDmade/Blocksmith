<?php

namespace VanDmade\Blocksmith\Verification;

use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\DocumentLinkMissing;
use VanDmade\Blocksmith\Models\Revision as BlocksmithRevision;
use VanDmade\Blocksmith\Models\Documents\Revision as DocumentRevision;
use VanDmade\Blocksmith\Services\HasherService;
use VanDmade\Blocksmith\Services\MerkleTreeService;
use VanDmade\Blocksmith\Services\RevisionService;

class DocumentIntegrityVerifier implements IntegrityVerifierInterface
{

    public function __construct(
        private readonly HasherService $hasherService,
        private readonly MerkleTreeService $merkleTreeService,
    ) {
    }

    public function verifyChain(BlocksmithRevision $revision): bool
    {
        $calculatedHash = $this->hasherService->computeHash(
            $revision->previous_hash,
            $revision->content_hash
        );
        if (!hash_equals($calculatedHash, $revision->hash)) {
            // Revision hash doesn't match the calculated hash
            return false;
        }
        if (config('blocksmith.verify_content', true)) {
            $documentRevision = DocumentRevision::query()
                ->where('blocksmith_revision_id', '=', $revision->id)
                ->first();
            if (!$documentRevision) {
                // The link is missing but this will not affect the chain
                DocumentLinkMissing::dispatch($revision);
            } elseif (!$documentRevision->verifyContent()) {
                // Yikes... Someone with developer access potentially modified the content... TAMPERRERERR
                return false;
            }
        }
        return true;

    }

    public function verifyMerkleRoot(BlocksmithRevision $revision): bool
    {
        $proof = $revision->proof;
        $anchorBatch = $revision->anchorBatch;
        if (empty($proof) || empty($anchorBatch)) {
            return false;
        }
        return $this->merkleTreeService->verify(
            $revision->hash,
            $proof->proof,
            $anchorBatch->merkle_root,
        );
    }

    public function verifyAnchorBatch(BlocksmithRevision $revision): bool
    {
        return $revision->anchorBatch?->status === AnchorBatchStatus::CONFIRMED;
    }

    public function verifySignature(BlocksmithRevision $revision): bool
    {
        return app(RevisionService::class)->verifySignature($revision);
    }

    public function verify(BlocksmithRevision $revision): VerificationResult
    {
        $result = new VerificationResult();
        if (!$this->verifyChain($revision)) {
            $result->fail('chain', 'Revision chain verification failed.');
        }
        if (!$this->verifyMerkleRoot($revision)) {
            $result->fail('merkle_root', 'Merkle root verification failed.');
        }
        if (!$this->verifyAnchorBatch($revision)) {
            $result->fail('anchor_batch', 'Anchor batch verification failed.');
        }
        if (!$this->verifySignature($revision)) {
            $result->fail('signature', 'Signature verification failed.');
        }
        return $result;
    }

}
