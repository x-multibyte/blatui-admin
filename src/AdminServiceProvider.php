<?php

declare(strict_types=1);

namespace BlatUI\Admin;

use BlatUI\Admin\Console\Commands\AdminCommand;
use BlatUI\Admin\Console\Commands\AdminInstallCommand;
use BlatUI\Admin\Console\Commands\BlatuiAdminCommand;
use BlatUI\Admin\Console\Installer\Actions\ArtisanRunner;
use BlatUI\Admin\Console\Installer\Actions\ProcessRunner;
use BlatUI\Admin\Console\Installer\Actions\StubManager;
use BlatUI\Admin\Console\Installer\Actions\ViteWiringManager;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use BlatUI\Admin\Console\Installer\Wizard\InstallWizard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blatui-admin.php', 'blatui-admin');

        $this->app->singleton(Admin::class);

        $this->app->singleton(EnvironmentDetector::class, fn (): EnvironmentDetector => new EnvironmentDetector);
        $this->app->singleton(ProcessRunner::class, fn (Application $app): ProcessRunner => new ProcessRunner($app->make(EnvironmentDetector::class)));
        $this->app->singleton(ViteWiringManager::class, fn (Application $app): ViteWiringManager => new ViteWiringManager($app->make(EnvironmentDetector::class)));
        $this->app->singleton(ArtisanRunner::class, fn (): ArtisanRunner => new ArtisanRunner);
        $this->app->singleton(StubManager::class, fn (): StubManager => new StubManager);
        $this->app->singleton(InstallWizard::class, fn (Application $app): InstallWizard => new InstallWizard(
            $app->make(EnvironmentDetector::class),
            $app->make(ProcessRunner::class),
            $app->make(ViteWiringManager::class),
            $app->make(ArtisanRunner::class),
            $app->make(StubManager::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/blatui-admin.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blatui-admin');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'blatui-admin');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/blatui-admin.php' => config_path('blatui-admin.php'),
        ], ['blatui-admin', 'blatui-admin-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/blatui-admin'),
        ], ['blatui-admin', 'blatui-admin-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/blatui-admin'),
        ], ['blatui-admin', 'blatui-admin-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/blatui-admin'),
        ], ['blatui-admin', 'blatui-admin-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['blatui-admin', 'blatui-admin-migrations']);

        $this->commands([
            AdminCommand::class,
            AdminInstallCommand::class,
            BlatuiAdminCommand::class,
        ]);
    }
}
