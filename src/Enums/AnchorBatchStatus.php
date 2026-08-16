<?php

namespace VanDmade\Blocksmith\Enums;

enum AnchorBatchStatus: string
{

    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case FAILED = 'failed';
    case EXHAUSTED = 'exhausted';

}
