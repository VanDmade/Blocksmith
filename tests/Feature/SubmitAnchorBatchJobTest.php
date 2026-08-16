<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Jobs\SubmitAnchorBatchJob;
use VanDmade\Blocksmith\Models\AnchorBatch;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Services\AnchorBatchService;
use VanDmade\Blocksmith\Tests\TestCase;

class SubmitAnchorBatchJobTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'alice.btc.calendar.opentimestamps.org/*' => Http::response('pending-proof-bytes'),
        ]);
    }

    public function test_handle_does_nothing_when_there_are_no_pending_revisions(): void
    {
        (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        $this->assertSame(0, AnchorBatch::count());
    }

    public function test_handle_batches_every_pending_revision(): void
    {
        $revisions = [
            Revision::create([
                'content_hash' => hash('sha256', 'a'),
                'hash' => hash('sha256', 'a'),
                'revision_number' => 1,
            ]),
            Revision::create([
                'content_hash' => hash('sha256', 'b'),
                'hash' => hash('sha256', 'b'),
                'revision_number' => 1,
            ]),
        ];
        (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        $this->assertSame(1, AnchorBatch::count());
        foreach ($revisions as $revision) {
            $this->assertNotNull($revision->fresh()->blocksmith_anchor_batch_id);
        }
    }

    public function test_handle_leaves_already_batched_revisions_out_of_a_new_batch(): void
    {
        $alreadyBatched = Revision::create([
            'content_hash' => hash('sha256', 'a'),
            'hash' => hash('sha256', 'a'),
            'revision_number' => 1,
        ]);
        $existingBatch = AnchorBatch::create([
            'merkle_root' => str_repeat('a', 64),
            'status' => 'submitted',
            'provider' => 'test',
        ]);
        $alreadyBatched->blocksmith_anchor_batch_id = $existingBatch->id;
        $alreadyBatched->save();
        $pendingOne = Revision::create([
            'content_hash' => hash('sha256', 'b'),
            'hash' => hash('sha256', 'b'),
            'revision_number' => 1,
        ]);
        $pendingTwo = Revision::create([
            'content_hash' => hash('sha256', 'c'),
            'hash' => hash('sha256', 'c'),
            'revision_number' => 1,
        ]);
        (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        // A new batch should be created no matter what, as new revisions can result in a new proof / merkle root.
        $this->assertSame(2, AnchorBatch::count());
        $this->assertSame($existingBatch->id, $alreadyBatched->fresh()->blocksmith_anchor_batch_id);
        $this->assertNotSame($existingBatch->id, $pendingOne->fresh()->blocksmith_anchor_batch_id);
        $this->assertSame(
            $pendingOne->fresh()->blocksmith_anchor_batch_id,
            $pendingTwo->fresh()->blocksmith_anchor_batch_id
        );
    }

    public function test_handle_waits_when_below_the_minimum_and_the_oldest_revision_is_still_fresh(): void
    {
        config(['blocksmith.anchoring.minimum_revisions_per_batch' => 2]);
        config(['blocksmith.anchoring.minimum_revisions_max_wait_minutes' => 60]);
        Revision::create([
            'content_hash' => hash('sha256', 'a'),
            'hash' => hash('sha256', 'a'),
            'revision_number' => 1,
        ]);
        (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        $this->assertSame(0, AnchorBatch::count());
    }

    public function test_handle_submits_below_the_minimum_once_the_oldest_revision_has_waited_long_enough(): void
    {
        config(['blocksmith.anchoring.minimum_revisions_per_batch' => 2]);
        config(['blocksmith.anchoring.minimum_revisions_max_wait_minutes' => 60]);
        $revision = Revision::create([
            'content_hash' => hash('sha256', 'a'),
            'hash' => hash('sha256', 'a'),
            'revision_number' => 1,
        ]);
        $revision->created_at = now()->subMinutes(61);
        $revision->save();
        (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        $this->assertSame(1, AnchorBatch::count());
        $this->assertNotNull($revision->fresh()->blocksmith_anchor_batch_id);
    }

    public function test_handle_submits_immediately_once_the_minimum_is_met_regardless_of_wait_time(): void
    {
        config(['blocksmith.anchoring.minimum_revisions_per_batch' => 2]);
        config(['blocksmith.anchoring.minimum_revisions_max_wait_minutes' => 60]);
        Revision::create([
            'content_hash' => hash('sha256', 'a'),
            'hash' => hash('sha256', 'a'),
            'revision_number' => 1,
        ]);
        Revision::create([
            'content_hash' => hash('sha256', 'b'),
            'hash' => hash('sha256', 'b'),
            'revision_number' => 1,
        ]);
        (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        $this->assertSame(1, AnchorBatch::count());
    }

    public function test_handle_logs_and_still_rethrows_when_the_provider_fails(): void
    {
        Event::fake([BlocksmithLog::class]);
        Revision::create([
            'content_hash' => hash('sha256', 'a'),
            'hash' => hash('sha256', 'a'),
            'revision_number' => 1,
        ]);
        Revision::create([
            'content_hash' => hash('sha256', 'b'),
            'hash' => hash('sha256', 'b'),
            'revision_number' => 1,
        ]);
        $this->app->bind(AnchorProviderInterface::class, fn() => new class implements AnchorProviderInterface {

            public function send(string $root): string
            {
                throw new RuntimeException('calendar unreachable');
            }
        
            public function check(string $root): ?string
            {
                return null;
            }
        
            public function getProvider(): string
            {
                return 'test';
            }

        });
        $thrown = null;
        try {
            (new SubmitAnchorBatchJob())->handle(app(AnchorBatchService::class));
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }
        $this->assertNotNull($thrown, 'Expected the exception to propagate out of handle().');
        $this->assertSame('calendar unreachable', $thrown->getMessage());
        Event::assertDispatched(
            BlocksmithLog::class,
            fn(BlocksmithLog $event) => $event->type === 'error' &&
                str_contains($event->message, 'calendar unreachable')
        );
    }

}
