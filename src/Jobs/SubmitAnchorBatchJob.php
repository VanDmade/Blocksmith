<?php

namespace VanDmade\Blocksmith\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Services\AnchorBatchService;

class SubmitAnchorBatchJob implements ShouldQueue
{

    use Dispatchable, Queueable;

    public function handle(AnchorBatchService $anchorBatchService): void
    {
        try {
            $this->submit($anchorBatchService);
        } catch (Throwable $exception) {
            BlocksmithLog::dispatch('error', $exception->getMessage(), [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            throw $exception;
        }
    }

    private function submit(AnchorBatchService $anchorBatchService): void
    {
        // Gets all of the revisions awaiting anchoring!
        $revisions = Revision::whereNull('blocksmith_anchor_batch_id')
            ->orderBy('id', 'asc')
            ->get();
        if ($revisions->isEmpty()) {
            return;
        }
        $minimum = config('blocksmith.anchoring.minimum_revisions_per_batch', 2);
        if ($revisions->count() < $minimum) {
            // Max time to wait until just giving up and sending a smaller revision batch (No lonely orphans!)
            $maxWaitMinutes = config('blocksmith.anchoring.minimum_revisions_max_wait_minutes', 60);
            $oldestPending = $revisions->first();
            if ($oldestPending->created_at->diffInMinutes(now()) < $maxWaitMinutes) {
                return;
            }
        }
        // Creates the anchor batch and uses the currently set provider to send the root for anchoring
        $anchorBatchService->create($revisions->all());
    }

}
