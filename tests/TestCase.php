<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests;

use BladeUI\Icons\BladeIconsServiceProvider;
use BlatUI\Admin\AdminServiceProvider;
use BlatUI\BlatuiServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use TailwindMerge\Laravel\TailwindMergeServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            AdminServiceProvider::class,
            BlatuiServiceProvider::class,
            TailwindMergeServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeLucideIconsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:6Cu/KuucjKJqbVPdpKG9UMgFYlDTveAuU5BOmqqExt8=');
        $app['config']->set('database.default', 'testing');
    }
}
