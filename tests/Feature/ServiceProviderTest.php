<?php

namespace VanDmade\Blocksmith\Tests\Feature;

use VanDmade\Blocksmith\Tests\TestCase;

class ServiceProviderTest extends TestCase
{

    public function test_the_package_config_is_merged(): void
    {
        $this->assertArrayHasKey('organization_model', config('blocksmith'));
    }

}
