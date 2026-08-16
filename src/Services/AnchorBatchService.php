<?php

namespace VanDmade\Blocksmith\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\AnchorBatchComplete;
use VanDmade\Blocksmith\Models\AnchorBatch;
use VanDmade\Blocksmith\Models\RevisionProof;

class AnchorBatchService
{

    public function __construct(
        private readonly MerkleTreeService $merkleTreeService
    ) {
        
    }

    public function find(AnchorBatch|int $anchorBatch): AnchorBatch
    {
        return $this->resolveAnchorBatch($anchorBatch);
    }

    public function findAwaitingVerification(): Collection
    {
        $days = config('blocksmith.anchoring.verify_anchor_interval');
        return AnchorBatch::where(function($query) {
                $query->where('status', '=', AnchorBatchStatus::CONFIRMED);
                if (config('blocksmith.anchoring.retry_failed_anchor_verification', false)) {
                    $query->orWhere('status', '=', AnchorBatchStatus::FAILED);
                }
            })
            ->whereNotNull('confirmed_at')
            ->where(function($query) {
                $query->whereNull('last_verified_at')
                    ->orWhere('last_verified_at', '<=', now()->subDays($days));
            })
            ->orderBy('last_verified_at', 'asc')
            ->get();
    }

    public function create(array $revisions): AnchorBatch
    {
        // Guarentees that the revisions came in correctly and people actually listen to what's required.
        $revisions = array_values($revisions);
        $leaves = [];
        foreach ($revisions as $revision) {
            $leaves[] = $revision->hash;
        }
        $this->merkleTreeService->build($leaves);
        $root = $this->merkleTreeService->computeRoot();
        $anchorBatch = DB::transaction(function() use ($revisions, $root) {
            $response = app(AnchorProviderInterface::class)->send($root);
            $anchorBatch = AnchorBatch::create([
                'submitted_at' => now(),
                'status' => AnchorBatchStatus::SUBMITTED,
                'merkle_root' => $root,
                'proof_reference' => $response,
            ]);
            // Iterates through the revisions to generate their proofs and the proof table entry
            foreach ($revisions as $leafIndex => $revision) {
                $proof = $this->merkleTreeService->generateProof($leafIndex);
                $revision->blocksmith_anchor_batch_id = $anchorBatch->id;
                $revision->save();
                RevisionProof::create([
                    'blocksmith_revision_id' => $revision->id,
                    // If empty it means the anchor batch only had one revision
                    'proof' => $proof,
                ]);
            }
            return $anchorBatch;
        });
        // Woohoo! The anchor batch was created and the revisions were linked
        AnchorBatchComplete::dispatch($anchorBatch);
        return $anchorBatch;
    }

    public function confirm(AnchorBatch|int $anchorBatch, string $proofReference): AnchorBatch
    {
        $anchorBatch = $this->resolveAnchorBatch($anchorBatch);
        $anchorBatch->update([
            'status' => AnchorBatchStatus::CONFIRMED,
            'confirmed_at' => now(),
            'proof_reference' => $proofReference,
        ]);
        return $anchorBatch;
    }

    public function fail(AnchorBatch|int $anchorBatch): AnchorBatch
    {
        $anchorBatch = $this->resolveAnchorBatch($anchorBatch);
        $anchorBatch->update([
            'status' => AnchorBatchStatus::FAILED,
            'failed_at' => now(),
        ]);
        // Update all revisions to have no anchor batch
        foreach ($anchorBatch->revisions as $revision) {
            $revision->blocksmith_anchor_batch_id = null;
            $revision->save();
        }
        return $anchorBatch;
    }

    public function update(AnchorBatch|int $anchorBatch, array $data): AnchorBatch
    {
        $anchorBatch = $this->resolveAnchorBatch($anchorBatch);
        $anchorBatch->update($data);
        return $anchorBatch;
    }

    private function resolveAnchorBatch(AnchorBatch|int $anchorBatch): AnchorBatch
    {
        return is_int($anchorBatch) ? AnchorBatch::findOrFail($anchorBatch) : $anchorBatch;
    }

}
