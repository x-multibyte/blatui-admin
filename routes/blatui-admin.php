<?php

declare(strict_types=1);

use BlatUI\Admin\Http\Controllers\AuthController;
use BlatUI\Admin\Http\Controllers\DashboardController;
use BlatUI\Admin\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

$prefix = (string) config('blatui-admin.route.prefix', 'admin');
$domain = config('blatui-admin.route.domain');
$middleware = (array) config('blatui-admin.route.middleware', ['web']);

Route::group([
    'prefix' => $prefix,
    'domain' => $domain,
    'middleware' => $middleware,
], function () {
    // Guest & Auth routes
    Route::get('auth/login', [AuthController::class, 'getLogin'])->name('admin.login');
    Route::post('auth/login', [AuthController::class, 'postLogin']);
    Route::post('auth/logout', [AuthController::class, 'getLogout'])->name('admin.logout');
    Route::get('auth/logout', [AuthController::class, 'getLogout']);

    // Authenticated admin routes
    Route::group(['middleware' => [Authenticate::class]], function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.home');

        // Resource pages, generated from `blatui-admin.resources` so an
        // application can swap any controller through config alone.
        //
        // The config key names the controller family; the URL segment is the
        // URI the Seeder's sidebar already links to. They differ for two
        // bundled resources, so they are mapped explicitly. Any other key
        // serves its own name.
        $segments = [
            'administrators' => 'users',
            'menus' => 'menu',
        ];

        foreach ((array) config('blatui-admin.resources', []) as $key => $controller) {
            $fallback = '\\BlatUI\\Admin\\Http\\Controllers\\Resources\\'.ucfirst((string) $key).'Controller';
            $class = is_string($controller) && class_exists($controller) ? $controller : $fallback;
            $segment = $segments[$key] ?? (string) $key;

            Route::group(['prefix' => 'auth/'.$segment], function () use ($class, $segment): void {
                Route::get('/', [$class, 'index'])->name("admin.{$segment}.index");
                Route::get('create', [$class, 'create'])->name("admin.{$segment}.create");
                Route::post('/', [$class, 'store'])->name("admin.{$segment}.store");
                // The static segment MUST precede {id} or {id} swallows it.
                Route::delete('batch-delete', [$class, 'batchDestroy'])->name("admin.{$segment}.batch-destroy");
                Route::get('{id}/edit', [$class, 'edit'])->whereNumber('id')->name("admin.{$segment}.edit");
                Route::put('{id}', [$class, 'update'])->whereNumber('id')->name("admin.{$segment}.update");
                Route::delete('{id}', [$class, 'destroy'])->whereNumber('id')->name("admin.{$segment}.destroy");
            });
        }
    });
});
