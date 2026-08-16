<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\AnchorBatchComplete;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Models\RevisionProof;
use VanDmade\Blocksmith\Services\AnchorBatchService;
use VanDmade\Blocksmith\Services\MerkleTreeService;
use VanDmade\Blocksmith\Tests\TestCase;

class AnchorBatchServiceTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        // Sets up fake location to send it
        Http::fake(['alice.btc.calendar.opentimestamps.org/*' => Http::response('pending-proof-bytes')]);
    }

    private function revisions(int $count): array
    {
        $revisions = [];
        for ($i = 0; $i < $count; $i++) {
            $revisions[] = Revision::create([
                'content_hash' => hash('sha256', 'content-'.$i),
                'hash' => hash('sha256', 'hash-'.$i),
                'revision_number' => 1,
            ]);
        }
        return $revisions;
    }

    public function test_create_submits_a_batch_with_the_correct_merkle_root(): void
    {
        $revisions = $this->revisions(2);
        // Creates the anchor batch with the two revisions
        $anchorBatch = app(AnchorBatchService::class)->create($revisions);
        $expectedRoot = new MerkleTreeService();
        $expectedRoot->build(array_map(fn(Revision $revision) => $revision->hash, $revisions));
        $this->assertTrue($anchorBatch->status === AnchorBatchStatus::SUBMITTED);
        // Validates that the merkle root stored within the anchor batch is the same as if I computed it separately
        $this->assertSame($expectedRoot->computeRoot(), $anchorBatch->merkle_root);
    }

    public function test_create_links_every_revision_to_the_new_batch(): void
    {
        $revisions = $this->revisions(3);
        $anchorBatch = app(AnchorBatchService::class)->create($revisions);
        foreach ($revisions as $revision) {
            // Makes sure that the revisions attached to the anchor batch received the id
            $this->assertSame($anchorBatch->id, $revision->fresh()->blocksmith_anchor_batch_id);
        }
    }

    public function test_create_stores_a_valid_write_once_proof_for_every_revision(): void
    {
        $revisions = $this->revisions(4);
        $anchorBatch = app(AnchorBatchService::class)->create($revisions);
        $merkleTree = new MerkleTreeService();
        foreach ($revisions as $revision) {
            $proofRow = RevisionProof::where('blocksmith_revision_id', '=', $revision->id)->first();
            $this->assertNotNull($proofRow, 'No proof was stored for revision '.$revision->id.'.');
            $this->assertTrue(
                $merkleTree->verify($revision->hash, $proofRow->proof, $anchorBatch->merkle_root),
                'Stored proof for revision '.$revision->id.' does not validate against the batch root.'
            );
        }
    }

    public function test_create_dispatches_anchor_batch_complete(): void
    {
        Event::fake([AnchorBatchComplete::class]);
        $revisions = $this->revisions(2);
        $anchorBatch = app(AnchorBatchService::class)->create($revisions);
        Event::assertDispatched(
            AnchorBatchComplete::class,
            fn(AnchorBatchComplete $event) => $event->anchorBatch->is($anchorBatch)
        );
    }

    public function test_confirm_transitions_the_batch_to_confirmed_and_stores_the_proof_reference(): void
    {
        $revisions = $this->revisions(2);
        $anchorBatch = app(AnchorBatchService::class)->create($revisions);
        // Marks the anchor batch as confirmed by the provider
        $confirmed = app(AnchorBatchService::class)->confirm($anchorBatch, 'the-real-confirmed-proof');
        // Verifies that the confirm method actually sets the correct values
        $this->assertTrue($confirmed->status === AnchorBatchStatus::CONFIRMED);
        $this->assertSame('the-real-confirmed-proof', $confirmed->proof_reference);
        $this->assertNotNull($confirmed->confirmed_at);
    }

    public function test_fail_transitions_the_batch_and_unlinks_its_revisions(): void
    {
        $revisions = $this->revisions(2);
        $anchorBatch = app(AnchorBatchService::class)->create($revisions);
        // Marks the anchor batch as failed by the provider
        $failed = app(AnchorBatchService::class)->fail($anchorBatch);
        // Verifies that the fail method actually sets the correct values
        $this->assertTrue($failed->status === AnchorBatchStatus::FAILED);
        $this->assertNotNull($failed->failed_at);
        foreach ($revisions as $revision) {
            // The revisions get released by the anchor batch to be added to a new anchor batch
            $this->assertNull($revision->fresh()->blocksmith_anchor_batch_id);
        }
    }

    public function test_find_resolves_an_anchor_batch_by_id(): void
    {
        $anchorBatch = app(AnchorBatchService::class)->create($this->revisions(2));
        $found = app(AnchorBatchService::class)->find($anchorBatch->id);
        $this->assertTrue($found->is($anchorBatch));
    }

}
