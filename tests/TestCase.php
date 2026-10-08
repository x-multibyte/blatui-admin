<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests;

use BlatUI\Admin\AdminServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
            \BlatUI\BlatuiServiceProvider::class,
            \TailwindMerge\Laravel\TailwindMergeServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \MallardDuck\LucideIcons\BladeLucideIconsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:6Cu/KuucjKJqbVPdpKG9UMgFYlDTveAuU5BOmqqExt8=');
        $app['config']->set('database.default', 'testing');
    }
}
