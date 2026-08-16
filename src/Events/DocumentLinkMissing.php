<?php

namespace VanDmade\Blocksmith\Events;

use Illuminate\Foundation\Events\Dispatchable;
use VanDmade\Blocksmith\Models\Revision;

class DocumentLinkMissing
{

    use Dispatchable;

    public function __construct(
        public readonly Revision $revision,
    ) {}

}
