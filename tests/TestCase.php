<?php

namespace VanDmade\Blocksmith\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use VanDmade\Blocksmith\BlocksmithServiceProvider;

abstract class TestCase extends Orchestra
{

    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            BlocksmithServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

}
