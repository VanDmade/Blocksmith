<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Jobs\CheckAnchorConfirmationJob;
use VanDmade\Blocksmith\Models\AnchorBatch;
use VanDmade\Blocksmith\Services\AnchorBatchService;
use VanDmade\Blocksmith\Tests\Support\InteractsWithOpenTimestamps;
use VanDmade\Blocksmith\Tests\TestCase;

class CheckAnchorConfirmationJobTest extends TestCase
{

    use InteractsWithOpenTimestamps;

    const OPENTIMESTAMPS_URL = 'alice.btc.calendar.opentimestamps.org/*';

    private function submittedBatch(string $root, ?Carbon $submittedAt = null): AnchorBatch
    {
        return AnchorBatch::create([
            'merkle_root' => $root,
            'status' => AnchorBatchStatus::SUBMITTED->value,
            'provider' => 'test-provider',
            'submitted_at' => $submittedAt ?? now(),
        ]);
    }

    private function createFakeEndpoint($response): void
    {
        Http::fake([
            self::OPENTIMESTAMPS_URL => Http::response($response),
        ]);
    }

    public function test_handle_confirms_a_batch_whose_digest_is_now_attested(): void
    {
        $batch = $this->submittedBatch(hash('sha256', 'confirmed-root'));
        $this->createFakeEndpoint($this->confirmedCalendarResponseBody());
        (new CheckAnchorConfirmationJob())->handle(app(AnchorBatchService::class));
        $this->assertTrue($batch->fresh()->status === AnchorBatchStatus::CONFIRMED);
        $this->assertNotNull($batch->fresh()->confirmed_at);
    }

    public function test_handle_leaves_a_still_pending_batch_alone(): void
    {
        $batch = $this->submittedBatch(hash('sha256', 'pending-root'));
        $this->createFakeEndpoint($this->pendingCalendarResponseBody());
        (new CheckAnchorConfirmationJob())->handle(app(AnchorBatchService::class));
        $this->assertTrue($batch->fresh()->status === AnchorBatchStatus::SUBMITTED);
    }

    public function test_handle_ignores_batches_that_are_not_currently_submitted(): void
    {
        $confirmedAlready = AnchorBatch::create([
            'merkle_root' => hash('sha256', 'already-confirmed'),
            'status' => AnchorBatchStatus::CONFIRMED->value,
            'provider' => 'test-provider',
        ]);
        $this->createFakeEndpoint($this->confirmedCalendarResponseBody());
        (new CheckAnchorConfirmationJob())->handle(app(AnchorBatchService::class));
        Http::assertNothingSent();
        $this->assertTrue($confirmedAlready->fresh()->status === AnchorBatchStatus::CONFIRMED);
    }

    public function test_handle_fails_a_still_pending_batch_once_it_exceeds_the_give_up_window(): void
    {
        config(['blocksmith.anchoring.max_time_until_give_up' => 60]);
        $batch = $this->submittedBatch(hash('sha256', 'stale-root'), now()->subMinutes(61));
        $this->createFakeEndpoint($this->pendingCalendarResponseBody());
        (new CheckAnchorConfirmationJob())->handle(app(AnchorBatchService::class));
        $this->assertTrue($batch->fresh()->status === AnchorBatchStatus::FAILED);
        $this->assertNotNull($batch->fresh()->failed_at);
    }

    public function test_handle_does_not_fail_a_still_pending_batch_within_the_give_up_window(): void
    {
        config(['blocksmith.anchoring.max_time_until_give_up' => 60]);
        $batch = $this->submittedBatch(hash('sha256', 'fresh-root'), now()->subMinutes(30));
        $this->createFakeEndpoint($this->pendingCalendarResponseBody());
        (new CheckAnchorConfirmationJob())->handle(app(AnchorBatchService::class));
        $this->assertTrue($batch->fresh()->status === AnchorBatchStatus::SUBMITTED);
    }

    public function test_handle_does_nothing_when_no_batches_are_awaiting_confirmation(): void
    {
        Http::fake();
        (new CheckAnchorConfirmationJob())->handle(app(AnchorBatchService::class));
        Http::assertNothingSent();
    }

}
