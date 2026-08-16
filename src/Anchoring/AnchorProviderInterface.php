<?php

namespace VanDmade\Blocksmith\Anchoring;

interface AnchorProviderInterface
{

    public function send(string $root): string;

    public function check(string $root): ?string;

    public function getProvider(): string;

}
