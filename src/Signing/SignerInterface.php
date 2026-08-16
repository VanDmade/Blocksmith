<?php

namespace VanDmade\Blocksmith\Signing;

interface SignerInterface
{

    public function sign(string $data, string $privateKey): string;

    public function verify(string $data, string $signature, string $publicKey): bool;

}
