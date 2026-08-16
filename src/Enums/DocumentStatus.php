<?php

namespace VanDmade\Blocksmith\Enums;

enum DocumentStatus: string
{

    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

}
