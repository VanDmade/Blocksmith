<?php

namespace VanDmade\Blocksmith\Http\Controllers;

use Illuminate\Http\JsonResponse;
use VanDmade\Blocksmith\Events\BlocksmithLog;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Verification\IntegrityVerifierInterface;
use Exception;

class VerificationController extends BlocksmithController
{

    public function __construct(
        protected IntegrityVerifierInterface $integrityVerifier,
    ) {
    }

    public function verify(Revision $revision): JsonResponse
    {
        try {
            // Verifies the integrity of the revision
            $results = $this->integrityVerifier->verify($revision);
            if ($results->passed()) {
                return $this->success([
                    'message' => __('blocksmith::verification.messages.valid'),
                ]);
            }
            return $this->success([
                'message' => __('blocksmith::verification.messages.invalid'),
                'errors' => $results->getErrors(),
            ], 400);
        } catch (Exception $error) {
            BlocksmithLog::dispatch('error', $error->getMessage(), ['exception' => get_class($error)]);
            return $this->error($error->getMessage(), 500);
        }
    }

}
