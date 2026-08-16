<?php

namespace VanDmade\Blocksmith\Events;

use Illuminate\Foundation\Events\Dispatchable;
use VanDmade\Blocksmith\Models\AnchorBatch;

class AnchorBatchComplete
{

    use Dispatchable;

    public function __construct(
        public readonly AnchorBatch $anchorBatch,
    ) {}

}
