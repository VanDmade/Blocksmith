<?php

namespace VanDmade\Blocksmith\Facades;

use Illuminate\Support\Facades\Facade;

class Blocksmith extends Facade
{

    protected static function getFacadeAccessor(): string
    {
        return 'blocksmith';
    }

}
