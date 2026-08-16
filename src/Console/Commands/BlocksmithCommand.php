<?php

namespace VanDmade\Blocksmith\Console\Commands;

use Illuminate\Console\Command;
use VanDmade\Blocksmith\Events\BlocksmithLog;

class BlocksmithCommand extends Command
{

    public function error($string, $verbosity = null): void
    {
        if (app()->runningInConsole()) {
            parent::error($string, $verbosity);
        } else {
            BlocksmithLog::dispatch('error', $string);
        }
    }

    public function line($string, $style = null, $verbosity = null): void
    {
        if (app()->runningInConsole()) {
            parent::line($string, $style, $verbosity);
        } else {
            BlocksmithLog::dispatch('info', $string);
        }
    }

}
