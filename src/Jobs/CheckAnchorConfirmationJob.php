<?php

namespace VanDmade\Blocksmith\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\AnchorBatch;
use VanDmade\Blocksmith\Services\AnchorBatchService;

class CheckAnchorConfirmationJob implements ShouldQueue
{

    use Dispatchable, Queueable;

    public function handle(AnchorBatchService $anchorBatchService): void
    {
        try {
            $this->checkConfirmations($anchorBatchService);
        } catch (Throwable $exception) {
            BlocksmithLog::dispatch('error', $exception->getMessage(), [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            throw $exception;
        }
    }

    private function checkConfirmations(AnchorBatchService $anchorBatchService): void
    {
        // Gets all of the batches awaiting their response
        $anchorBatches = AnchorBatch::where('status', '=', AnchorBatchStatus::SUBMITTED)->get();
        if ($anchorBatches->isEmpty()) {
            return;
        }
        foreach ($anchorBatches as $anchorBatch) {
            $proofReference = app(AnchorProviderInterface::class)->check($anchorBatch->merkle_root);
            if (!empty($proofReference)) {
                $anchorBatch = $anchorBatchService->confirm($anchorBatch, $proofReference);
            } else {
                // The anchor provider still hasn't confirmed the batch
                $giveUpMinutes = config('blocksmith.anchoring.max_time_until_give_up', 48 * 60);
                if (!is_null($anchorBatch->submitted_at) &&
                    $anchorBatch->submitted_at->diffInMinutes(now()) > $giveUpMinutes) {
                    // The anchor provider has not confirmed the batch within 48 hours, so we mark it as failed.
                    $anchorBatch = $anchorBatchService->fail($anchorBatch);
                }
            }
        }
    }

}
