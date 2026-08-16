<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use VanDmade\Blocksmith\Jobs\CheckAnchorBatchThresholdJob;
use VanDmade\Blocksmith\Jobs\SubmitAnchorBatchJob;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Tests\TestCase;

class CheckAnchorBatchThresholdJobTest extends TestCase
{

    private function createPendingRevisions(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Revision::create([
                'content_hash' => hash('sha256', 'r'.$i),
                'hash' => hash('sha256', 'r'.$i),
                'revision_number' => 1,
            ]);
        }
    }

    public function test_handle_does_not_dispatch_below_the_threshold(): void
    {
        Bus::fake();
        config(['blocksmith.anchoring.batch_size_threshold' => 5]);
        $this->createPendingRevisions(4);
        (new CheckAnchorBatchThresholdJob())->handle();
        Bus::assertNotDispatched(SubmitAnchorBatchJob::class);
    }

    public function test_handle_dispatches_the_submit_job_once_the_threshold_is_met(): void
    {
        Bus::fake();
        config(['blocksmith.anchoring.batch_size_threshold' => 5]);
        $this->createPendingRevisions(5);
        (new CheckAnchorBatchThresholdJob())->handle();
        Bus::assertDispatched(SubmitAnchorBatchJob::class);
    }

    public function test_handle_dispatches_the_submit_job_when_above_the_threshold(): void
    {
        Bus::fake();
        config(['blocksmith.anchoring.batch_size_threshold' => 5]);
        $this->createPendingRevisions(9);
        (new CheckAnchorBatchThresholdJob())->handle();
        Bus::assertDispatched(SubmitAnchorBatchJob::class);
    }

}
