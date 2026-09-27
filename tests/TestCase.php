<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests;

use BlatUI\Admin\AdminServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:6Cu/KuucjKJqbVPdpKG9UMgFYlDTveAuU5BOmqqExt8=');
    }
}
