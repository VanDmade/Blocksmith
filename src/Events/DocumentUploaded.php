<?php

namespace VanDmade\Blocksmith\Events;

use Illuminate\Foundation\Events\Dispatchable;
use VanDmade\Blocksmith\Models\Documents\Document;
use VanDmade\Blocksmith\Models\Revision;

class DocumentUploaded
{

    use Dispatchable;

    public function __construct(
        public readonly Document $document,
        public readonly Revision $revision,
    ) {}

}
