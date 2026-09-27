<?php

declare(strict_types=1);

namespace BlatUI\Admin;

use BlatUI\Admin\Console\Commands\AdminCommand;
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
        ]);
    }
}
