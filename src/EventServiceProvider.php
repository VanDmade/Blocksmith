<?php

namespace VanDmade\Blocksmith;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use VanDmade\Blocksmith\Listeners\LogListener;

class EventServiceProvider extends ServiceProvider
{

    protected $subscribe = [
        LogListener::class,
    ];

}
