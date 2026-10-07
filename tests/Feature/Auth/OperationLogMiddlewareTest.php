<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Feature\Auth;

use BlatUI\Admin\Http\Middleware\Authenticate;
use BlatUI\Admin\Http\Middleware\OperationLog as OperationLogMiddleware;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\OperationLog;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    Route::middleware(['web', Authenticate::class, OperationLogMiddleware::class])
        ->prefix('admin/test-log')
        ->group(function () {
            Route::get('ping', fn () => response('pong'));
            Route::post('action', fn () => response('action ok'));
            Route::get('auth/logs-bypass', fn () => response('bypass ok'));
        });
});

test('middleware logs authenticated admin requests', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $this->post('/admin/test-log/action', ['foo' => 'bar'])->assertOk();

    $log = OperationLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->method)->toBe('POST')
        ->and($log->path)->toContain('test-log/action')
        ->and(json_decode((string) $log->input, true))->toMatchArray(['foo' => 'bar']);
});

test('middleware skips unauthenticated requests', function () {
    $this->post('/admin/test-log/action', ['foo' => 'bar']);

    expect(OperationLog::query()->count())->toBe(0);
});

test('middleware skips when operation log is disabled', function () {
    config(['blatui-admin.operation_log.enable' => false]);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $this->post('/admin/test-log/action', ['foo' => 'bar'])->assertOk();

    expect(OperationLog::query()->count())->toBe(0);
});

test('middleware skips disallowed HTTP methods', function () {
    config(['blatui-admin.operation_log.allowed_methods' => ['POST']]);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $this->get('/admin/test-log/ping')->assertOk();

    expect(OperationLog::query()->count())->toBe(0);
});

test('middleware skips excepted paths', function () {
    config(['blatui-admin.operation_log.except' => ['admin/test-log/auth/logs*']]);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $this->get('/admin/test-log/auth/logs-bypass')->assertOk();

    expect(OperationLog::query()->count())->toBe(0);
});

test('middleware masks secret fields in input payload', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $this->post('/admin/test-log/action', [
        'username' => 'superadmin',
        'password' => 'my-plain-secret',
        'password_confirmation' => 'my-plain-secret',
        'nested' => [
            'password' => 'nested-secret',
        ],
    ])->assertOk();

    $log = OperationLog::query()->latest('id')->firstOrFail();
    $input = json_decode((string) $log->input, true);

    expect($input['username'])->toBe('superadmin')
        ->and($input['password'])->toBe('******')
        ->and($input['password_confirmation'])->toBe('******')
        ->and($input['nested']['password'])->toBe('******');
});
