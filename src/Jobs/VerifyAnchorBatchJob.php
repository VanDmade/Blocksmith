<?php

namespace VanDmade\Blocksmith\Jobs;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use VanDmade\Blocksmith\Anchoring\AnchorProviderInterface;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\AnchorBatchConfirmed;
use VanDmade\Blocksmith\Events\AnchorBatchFailed;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Services\AnchorBatchService;
use Throwable;

class VerifyAnchorBatchJob implements ShouldQueue
{

    use Dispatchable, Queueable;

    public function handle(
        AnchorBatchService $anchorBatchService
    ): void {
        try {
            $maxAmountOfBatchesToVerify = config('blocksmith.anchoring.max_batches_to_verify_per_job', 50);
            $anchorBatches = $anchorBatchService->findAwaitingVerification();
            foreach ($anchorBatches as $i => $anchorBatch) {
                if ($i >= $maxAmountOfBatchesToVerify) {
                    // Limits the number of batches to verify per job to avoid MASSIVE load times
                    break;
                }
                $response = app(AnchorProviderInterface::class)
                    ->check($anchorBatch->merkle_root);
                $anchorBatch->last_verified_at = now();
                if (is_null($response)) {
                    $anchorBatch->status = $anchorBatch->status === AnchorBatchStatus::FAILED ?
                        AnchorBatchStatus::EXHAUSTED : AnchorBatchStatus::FAILED;
                    $anchorBatch->failed_at = now();
                    // The anchor batch came back and was not confirmed so something is up!
                    AnchorBatchFailed::dispatch($anchorBatch);
                } elseif ($anchorBatch->status != AnchorBatchStatus::CONFIRMED) {
                    $anchorBatch = $anchorBatchService->confirm($anchorBatch, $response);
                    AnchorBatchConfirmed::dispatch($anchorBatch);
                }
                $anchorBatch->save();
            }
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
