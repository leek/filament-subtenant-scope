<?php

declare(strict_types=1);

namespace Leek\FilamentSubtenantScope\Tests;

use Leek\FilamentSubtenantScope\FilamentSubtenantScopeServiceProvider;
use Leek\FilamentSubtenantScope\Tests\Fixtures\Region;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Region::ensureTable();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FilamentSubtenantScopeServiceProvider::class,
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
    }
}
