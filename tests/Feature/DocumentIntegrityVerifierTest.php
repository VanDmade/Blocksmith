<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use VanDmade\Blocksmith\Enums\AnchorBatchStatus;
use VanDmade\Blocksmith\Events\DocumentLinkMissing;
use VanDmade\Blocksmith\Models\AnchorBatch;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Revision;
use VanDmade\Blocksmith\Models\RevisionProof;
use VanDmade\Blocksmith\Models\SigningKey;
use VanDmade\Blocksmith\Services\DocumentService;
use VanDmade\Blocksmith\Services\MerkleTreeService;
use VanDmade\Blocksmith\Services\RevisionService;
use VanDmade\Blocksmith\Tests\TestCase;
use VanDmade\Blocksmith\Verification\DocumentIntegrityVerifier;

class DocumentIntegrityVerifierTest extends TestCase
{

    private function documentWithContent(
        string $content = 'hello world',
        string $path = 'documents/test.pdf'
    ): array {
        Storage::fake('local');
        Storage::disk('local')->put($path, $content);
        $document = app(DocumentService::class)->create(
            ['name' => 'Test Document'],
            hash('sha256', $content),
            [
                'disk' => 'local',
                'path' => $path,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($content),
            ]
        );
        return [$document, $document->currentRevision];
    }

    private function bareRevision(string $hash, ?string $previousHash = null): Revision
    {
        return Revision::create([
            'previous_hash' => $previousHash,
            'content_hash' => hash('sha256', $hash),
            'hash' => $hash,
            'revision_number' => 1,
        ]);
    }

    public function test_verify_chain_passes_for_a_correctly_chained_revision_with_matching_content(): void
    {
        [$ignore, $revision] = $this->documentWithContent();
        $this->assertTrue(app(DocumentIntegrityVerifier::class)->verifyChain($revision));
    }

    public function test_verify_chain_fails_when_the_hash_does_not_match_previous_plus_content(): void
    {
        [$ignore, $revision] = $this->documentWithContent();
        $revision->hash = str_repeat('f', 64);
        $revision->save();
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifyChain($revision->fresh()));
    }

    public function test_verify_chain_fails_when_the_file_on_disk_no_longer_matches_the_content_hash(): void
    {
        [$ignore, $revision] = $this->documentWithContent('original content', 'documents/tampered.pdf');
        Storage::disk('local')->put('documents/tampered.pdf', 'swapped content');
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifyChain($revision));
    }

    public function test_verify_chain_passes_and_reports_document_link_missing_when_there_is_no_pivot(): void
    {
        Event::fake([DocumentLinkMissing::class]);
        // A bare, resource-agnostic revision - never linked to a Document at all.
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        $this->assertTrue(app(DocumentIntegrityVerifier::class)->verifyChain($revision));
        Event::assertDispatched(
            DocumentLinkMissing::class,
            fn(DocumentLinkMissing $event) => $event->revision->is($revision)
        );
    }

    public function test_verify_chain_skips_the_content_check_entirely_when_verify_content_is_disabled(): void
    {
        $document = 'documents/config-off.pdf';
        [$ignore, $revision] = $this->documentWithContent('original', $document);
        Storage::disk('local')->put($document, 'swapped - should never be read');
        config(['blocksmith.verify_content' => false]);
        $this->assertTrue(app(DocumentIntegrityVerifier::class)->verifyChain($revision));
    }

    public function test_verify_merkle_root_passes_for_a_correctly_proved_revision(): void
    {
        $revisionA = $this->bareRevision(hash('sha256', 'a'));
        $revisionB = $this->bareRevision(hash('sha256', 'b'));
        $tree = new MerkleTreeService();
        $tree->build([
            $revisionA->hash,
            $revisionB->hash,
        ]);
        $anchorBatch = AnchorBatch::create([
            'merkle_root' => $tree->computeRoot(),
            'status' => AnchorBatchStatus::SUBMITTED->value,
            'provider' => 'test-provider',
        ]);
        $revisionA->blocksmith_anchor_batch_id = $anchorBatch->id;
        $revisionA->save();
        RevisionProof::create([
            'blocksmith_revision_id' => $revisionA->id,
            'proof' => $tree->generateProof(0),
        ]);
        $this->assertTrue(app(DocumentIntegrityVerifier::class)->verifyMerkleRoot($revisionA->fresh()));
    }

    public function test_verify_merkle_root_fails_when_there_is_no_stored_proof(): void
    {
        $revision = $this->bareRevision(hash('sha256', 'a'));
        $anchorBatch = AnchorBatch::create([
            'merkle_root' => str_repeat('a', 64),
            'status' => AnchorBatchStatus::SUBMITTED,
            'provider' => 'test-provider',
        ]);
        $revision->blocksmith_anchor_batch_id = $anchorBatch->id;
        $revision->save();
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifyMerkleRoot($revision->fresh()));
    }

    public function test_verify_merkle_root_fails_when_there_is_no_anchor_batch(): void
    {
        $revision = $this->bareRevision(hash('sha256', 'a'));
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifyMerkleRoot($revision));
    }

    public function test_verify_anchor_batch_is_true_only_once_locally_confirmed(): void
    {
        $revision = $this->bareRevision(hash('sha256', 'a'));
        $anchorBatch = AnchorBatch::create([
            'merkle_root' => str_repeat('a', 64),
            'status' => AnchorBatchStatus::SUBMITTED,
            'provider' => 'test-provider',
        ]);
        $revision->blocksmith_anchor_batch_id = $anchorBatch->id;
        $revision->save();
        // The anchor batch is not confirmed and therefore it cannot be verified
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifyAnchorBatch($revision->fresh()));
        $anchorBatch->update([
            'status' => AnchorBatchStatus::CONFIRMED,
        ]);
        $this->assertTrue(app(DocumentIntegrityVerifier::class)->verifyAnchorBatch($revision->fresh()));
    }

    public function test_verify_anchor_batch_is_false_when_there_is_no_batch_at_all(): void
    {
        $revision = $this->bareRevision(hash('sha256', 'a'));
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifyAnchorBatch($revision));
    }

    public function test_verify_signature_delegates_to_the_revision_service(): void
    {
        $unsigned = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        $this->assertFalse(app(DocumentIntegrityVerifier::class)->verifySignature($unsigned));
        $keypair = sodium_crypto_sign_keypair();
        // Signs the reivision using similar logic that is in the built Ed25519Signer.php
        $signingKey = SigningKey::create([
            'public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'private_key' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
            'custodial' => true,
        ]);
        // Creates the reivison and signs it
        $signed = app(RevisionService::class)->create(
            null,
            str_repeat('b', 64),
            1,
            $signingKey
        );
        // The signature is valid since it's the same one that was used to sign the revision
        $this->assertTrue(app(DocumentIntegrityVerifier::class)->verifySignature($signed));
    }

    public function test_verify_aggregates_every_check_and_reports_each_failure_separately(): void
    {
        $revision = Revision::create([
            'content_hash' => hash('sha256', 'x'),
            'hash' => 'not-the-real-hash',
            'revision_number' => 1,
        ]);
        $result = app(DocumentIntegrityVerifier::class)->verify($revision);
        $this->assertFalse($result->passed());
        // The hash was deliberately tampered with
        $this->assertTrue($result->checkFailed('chain'));
        // The anchor batch doesn't exist
        $this->assertTrue($result->checkFailed('merkle_root'));
        $this->assertTrue($result->checkFailed('anchor_batch'));
        // The revision was never signed
        $this->assertTrue($result->checkFailed('signature'));
        $this->assertCount(4, $result->getErrors());
    }

}
