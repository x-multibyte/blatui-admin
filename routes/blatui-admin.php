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
    });
});
