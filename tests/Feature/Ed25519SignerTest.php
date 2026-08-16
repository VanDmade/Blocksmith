<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Signing\Ed25519Signer;
use VanDmade\Blocksmith\Tests\TestCase;

class Ed25519SignerTest extends TestCase
{

    private function keypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        return [
            'public' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
            'private' => sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
        ];
    }

    public function test_sign_then_verify_round_trips(): void
    {
        $keys = $this->keypair();
        $signer = new Ed25519Signer();
        $signature = $signer->sign('some-hash-to-sign', $keys['private']);
        $this->assertTrue($signer->verify('some-hash-to-sign', $signature, $keys['public']));
    }

    public function test_verify_fails_when_the_signed_data_was_tampered_with(): void
    {
        $keys = $this->keypair();
        $signer = new Ed25519Signer();
        $signature = $signer->sign('original-data', $keys['private']);
        // Shows that the data when changed will not verify correctly
        $this->assertFalse($signer->verify('tampered-data', $signature, $keys['public']));
    }

    public function test_verify_fails_with_the_wrong_public_key(): void
    {
        $keys = $this->keypair();
        $otherKeys = $this->keypair();
        $signer = new Ed25519Signer();
        $signature = $signer->sign('some-data', $keys['private']);
        // Compares the signature with another keys public key to prove that the software will actually block it
        $this->assertFalse($signer->verify('some-data', $signature, $otherKeys['public']));
    }

    public function test_verify_fails_when_the_signature_itself_was_tampered_with(): void
    {
        $keys = $this->keypair();
        $signer = new Ed25519Signer();
        // Signs the data with the private key
        $signature = $signer->sign('some-data', $keys['private']);
        $tamperedSignature = str_repeat('a', strlen($signature));
        // Compares the tampered signature to prove that the public key only works with the O.G.
        $this->assertFalse($signer->verify('some-data', $tamperedSignature, $keys['public']));
    }

}
