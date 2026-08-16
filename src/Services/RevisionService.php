<?php

namespace VanDmade\Blocksmith\Services;

use VanDmade\Blocksmith\Enums\RevisionStatus;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Models\SigningKey;
use VanDmade\Blocksmith\Signing\SignerInterface;

class RevisionService
{

    public function create(
        ?string $previousHash,
        string $contentHash,
        int $revisionNumber,
        ?SigningKey $signingKey = null,
        ?string $revisionReason = null,
    ): Revision {
        $hash = app(HasherService::class)->computeHash($previousHash, $contentHash);
        $signature = null;
        if ($signingKey && $signingKey->custodial) {
            $signature = app(SignerInterface::class)->sign($hash, $signingKey->private_key);
        }
        return Revision::create([
            'blocksmith_signing_key_id' => $signingKey?->id,
            'previous_hash' => $previousHash,
            'content_hash' => $contentHash,
            'hash' => $hash,
            'revision_number' => $revisionNumber,
            'revision_reason' => $revisionReason,
            'status' => $signature ? RevisionStatus::SIGNED : RevisionStatus::PENDING,
            'signature' => $signature,
        ]);
    }

    public function attachSignature(Revision|int $revision, string $signature): Revision
    {
        $revision = $this->resolveRevision($revision);
        $revision->status = RevisionStatus::SIGNED;
        $revision->signature = $signature;
        $revision->save();
        return $revision;
    }

    public function verifySignature(Revision|int $revision): bool
    {
        $revision = $this->resolveRevision($revision);
        if (!$revision->signature || !$revision->signingKey) {
            return false;
        }
        return app(SignerInterface::class)->verify(
            $revision->hash,
            $revision->signature,
            $revision->signingKey->public_key,
        );
    }

    public function delete(Revision|int $revision): bool
    {
        return $this->resolveRevision($revision)->delete() ?? false;
    }

    private function resolveRevision(Revision|int $revision): Revision
    {
        return is_int($revision) ? Revision::findOrFail($revision) : $revision;
    }

}
