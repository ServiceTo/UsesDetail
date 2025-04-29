<?php

namespace ServiceTo\UsesDetail\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ServiceTo\UsesDetail\Tests\Models\TestModel;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
} 