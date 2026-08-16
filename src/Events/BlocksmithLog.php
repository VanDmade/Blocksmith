<?php

namespace VanDmade\Blocksmith\Events;

use Illuminate\Foundation\Events\Dispatchable;

class BlocksmithLog
{

    use Dispatchable;

    public function __construct(
        public string $type,
        public string $message,
        public array $context = []
    ) {}

}
