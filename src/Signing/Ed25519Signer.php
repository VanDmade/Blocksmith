<?php

namespace VanDmade\Blocksmith\Signing;

class Ed25519Signer implements SignerInterface
{

    public function sign(string $data, string $privateKey): string
    {
        $signature = sodium_crypto_sign_detached(
            $data,
            sodium_hex2bin($privateKey)
        );
        return sodium_bin2hex($signature);
    }

    public function verify(
        string $data,
        string $signature,
        string $publicKey
    ): bool {
        return sodium_crypto_sign_verify_detached(
            sodium_hex2bin($signature),
            $data,
            sodium_hex2bin($publicKey)
        );
    }

}
