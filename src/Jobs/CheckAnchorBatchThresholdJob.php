<?php

namespace VanDmade\Blocksmith\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Revision;

class CheckAnchorBatchThresholdJob implements ShouldQueue
{

    use Dispatchable, Queueable;

    public function handle(): void
    {
        try {
            $threshold = config('blocksmith.anchoring.batch_size_threshold', 500);
            $pendingCount = Revision::whereNull('blocksmith_anchor_batch_id')->count();
            // Makes sure the system isn't stacking up too many revisions causing as LARGE LARGE merkle tree
            if ($pendingCount < $threshold) {
                return;
            }
            // Submits the batch as the threshold has been met or exceeded!
            SubmitAnchorBatchJob::dispatch();
        } catch (Throwable $exception) {
            BlocksmithLog::dispatch('error', $exception->getMessage(), [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            throw $exception;
        }
    }

}
