<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Enums\RevisionStatus;
use VanDmade\Blocksmith\Models\SigningKey;
use VanDmade\Blocksmith\Services\HasherService;
use VanDmade\Blocksmith\Services\RevisionService;
use VanDmade\Blocksmith\Tests\TestCase;

class RevisionServiceTest extends TestCase
{

    private function custodialSigningKey(): SigningKey
    {
        $keypair = sodium_crypto_sign_keypair();
        return SigningKey::create([
            'public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'private_key' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
            'custodial' => true,
        ]);
    }

    public function test_create_without_a_signing_key_is_pending_and_unsigned(): void
    {
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        $this->assertNull($revision->signature);
        $this->assertTrue($revision->status === RevisionStatus::PENDING);
    }

    public function test_create_with_a_custodial_signing_key_is_signed_and_verifiable(): void
    {
        $signingKey = $this->custodialSigningKey();
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1, $signingKey);
        $this->assertNotNull($revision->signature);
        $this->assertTrue($revision->status === RevisionStatus::SIGNED);
        $this->assertTrue(app(RevisionService::class)->verifySignature($revision));
    }

    public function test_create_with_a_non_custodial_signing_key_never_calls_sign(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $signingKey = SigningKey::create([
            'public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            // If the custodial flag is false the private key is NOT on the server... 
            'custodial' => false,
        ]);
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1, $signingKey);
        $this->assertNull($revision->signature);
        $this->assertTrue($revision->status === RevisionStatus::PENDING);
    }

    public function test_create_computes_the_hash_from_the_previous_hash_and_content_hash(): void
    {
        $previousHash = str_repeat('c', 64);
        $contentHash = str_repeat('d', 64);
        $revision = app(RevisionService::class)->create($previousHash, $contentHash, 2);
        // The previous hash should always be the hash of the previous revision
        $this->assertSame(
            app(HasherService::class)->computeHash($previousHash, $contentHash),
            $revision->hash
        );
    }

    public function test_attach_signature_marks_the_revision_signed(): void
    {
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        $updated = app(RevisionService::class)->attachSignature($revision, 'a-client-supplied-signature');
        $this->assertSame('a-client-supplied-signature', $updated->signature);
        $this->assertTrue($updated->status === RevisionStatus::SIGNED);
    }

    public function test_verify_signature_is_false_when_there_is_no_signature_at_all(): void
    {
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        $this->assertFalse(app(RevisionService::class)->verifySignature($revision));
    }

    public function test_verify_signature_is_false_when_a_signature_exists_but_no_signing_key_is_linked(): void
    {
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        app(RevisionService::class)->attachSignature($revision, 'orphaned-signature');
        $this->assertFalse(app(RevisionService::class)->verifySignature($revision->fresh()));
    }

    public function test_verify_signature_is_false_when_the_signature_does_not_match(): void
    {
        $signingKey = $this->custodialSigningKey();
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1, $signingKey);
        $revision->signature = str_repeat('0', strlen($revision->signature));
        $revision->save();
        $this->assertFalse(app(RevisionService::class)->verifySignature($revision));
    }

    public function test_delete_soft_deletes_the_revision(): void
    {
        $revision = app(RevisionService::class)->create(null, str_repeat('a', 64), 1);
        // It is crucial we never fully delete a revision as it breaks the chain
        $this->assertTrue(app(RevisionService::class)->delete($revision));
        $this->assertSoftDeleted($revision);
    }

}
