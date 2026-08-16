<?php

namespace VanDmade\Blocksmith\Verification;

use VanDmade\Blocksmith\Models\Revision;

interface IntegrityVerifierInterface
{

    public function verifyChain(Revision $revision): bool;

    public function verifyMerkleRoot(Revision $revision): bool;

    public function verifyAnchorBatch(Revision $revision): bool;

    public function verifySignature(Revision $revision): bool;

    public function verify(Revision $revision): VerificationResult;

}
