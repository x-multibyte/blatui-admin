<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Feature\Support;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Contracts\Repository;
use BlatUI\Admin\Form;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use BlatUI\Admin\Repositories\EloquentRepository;
use BlatUI\Admin\Support\Logger;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Http\Request;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

afterEach(function () {
    Admin::logger()->setLogger(null);
    Admin::setLogger(null);
    Mockery::close();
});

test('Admin::logger returns Logger instance implementing LoggerInterface', function () {
    $logger = Admin::logger();

    expect($logger)->toBeInstanceOf(Logger::class)
        ->and($logger)->toBeInstanceOf(LoggerInterface::class);
});

test('Logger enriches context automatically with admin session and request data', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->once()
        ->with('info', 'Test message', Mockery::on(function (array $context) use ($admin) {
            return $context['admin_user_id'] === $admin->id
                && $context['admin_user'] === 'admin'
                && array_key_exists('ip', $context)
                && array_key_exists('method', $context)
                && array_key_exists('path', $context)
                && $context['custom_key'] === 'custom_val';
        }));

    $logger = new Logger($mock);
    $logger->info('Test message', ['custom_key' => 'custom_val']);
});

test('Admin::log static method delegates to admin logger', function () {
    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->once()
        ->with('error', 'Critical failure', Mockery::type('array'));

    Admin::logger()->setLogger($mock);

    Admin::log('error', 'Critical failure', ['foo' => 'bar']);
});

test('Logger does not write when logging is disabled in configuration', function () {
    config(['blatui-admin.logging.enable' => false]);

    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldNotReceive('log');

    $logger = new Logger($mock);
    $logger->info('Should not be logged');
});

test('Logger filters messages below configured logging.level threshold', function () {
    config([
        'blatui-admin.logging.enable' => true,
        'blatui-admin.logging.level' => 'warning',
    ]);

    $mock = Mockery::mock(LoggerInterface::class);
    // info is below warning, must not be logged
    $mock->shouldNotReceive('log')->with('info', 'Should be filtered out', Mockery::type('array'));
    // error is above warning, must be logged
    $mock->shouldReceive('log')
        ->once()
        ->with('error', 'Should be written', Mockery::type('array'));

    $logger = new Logger($mock);
    $logger->info('Should be filtered out');
    $logger->error('Should be written');
});

test('AuthController logs info on successful login', function () {
    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->with('info', 'Admin user logged in successfully', Mockery::on(function (array $context) {
            return $context['username'] === 'admin';
        }))
        ->atLeast()->once();
    $mock->shouldReceive('log')->byDefault();

    Admin::logger()->setLogger($mock);

    $this->post('/admin/auth/login', [
        'username' => 'admin',
        'password' => 'admin',
    ])->assertRedirect('/admin');
});

test('AuthController logs warning on failed login', function () {
    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->with('warning', 'Admin login failed', Mockery::on(function (array $context) {
            return $context['username'] === 'admin';
        }))
        ->atLeast()->once();
    $mock->shouldReceive('log')->byDefault();

    Admin::logger()->setLogger($mock);

    $this->post('/admin/auth/login', [
        'username' => 'admin',
        'password' => 'wrong-password',
    ]);
});

test('AuthController logs info on logout', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->with('info', 'Admin user logged out', Mockery::type('array'))
        ->atLeast()->once();
    $mock->shouldReceive('log')->byDefault();

    Admin::logger()->setLogger($mock);

    $this->post('/admin/auth/logout')->assertRedirect('/admin/auth/login');
});

test('Permission middleware logs warning and throws PermissionDeniedException on unauthorized access', function () {
    /** @var Role $role */
    $role = Role::query()->create(['name' => 'Limited', 'slug' => 'limited']);
    /** @var Administrator $user */
    $user = Administrator::query()->create([
        'username' => 'limited_user',
        'name' => 'Limited User',
        'password' => bcrypt('password'),
    ]);
    $user->roles()->attach($role);

    $this->actingAs($user, 'admin');

    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->with('warning', 'Admin access denied', Mockery::type('array'))
        ->atLeast()->once();
    $mock->shouldReceive('log')->byDefault();

    Admin::logger()->setLogger($mock);

    $response = $this->get('/admin/auth/roles');
    $response->assertStatus(403);
});

test('ResourceController logs error on store, update, destroy, and batchDestroy failures', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->with('error', Mockery::pattern('/Resource (store|update|destroy|batch destroy) failed/'), Mockery::type('array'))
        ->times(4);
    $mock->shouldReceive('log')->byDefault();

    Admin::logger()->setLogger($mock);

    $controller = new class extends ResourceController
    {
        public function title(): string
        {
            return 'Test';
        }

        protected function resource(): string
        {
            return '/admin/test';
        }

        protected function grid(): Grid
        {
            return Grid::make();
        }

        protected function form(bool $editing = false, ?int $id = null): Form
        {
            $repo = Mockery::mock(Repository::class);
            $repo->shouldReceive('store')->andThrow(new RuntimeException('DB store error'));
            $repo->shouldReceive('update')->andThrow(new RuntimeException('DB update error'));

            return Form::make($repo);
        }

        protected function repositoryFor(): EloquentRepository
        {
            $repo = Mockery::mock(EloquentRepository::class);
            $repo->shouldReceive('edit')->andReturn(new Role);
            $repo->shouldReceive('destroy')->andThrow(new RuntimeException('DB destroy error'));

            return $repo;
        }
    };

    expect(fn () => $controller->store(Request::create('/admin/test', 'POST')))
        ->toThrow(RuntimeException::class, 'DB store error');

    expect(fn () => $controller->update(Request::create('/admin/test/1', 'PUT'), 1))
        ->toThrow(RuntimeException::class, 'DB update error');

    expect(fn () => $controller->destroy(1))
        ->toThrow(RuntimeException::class, 'DB destroy error');

    expect(fn () => $controller->batchDestroy(Request::create('/admin/test/batch-delete', 'POST', ['ids' => [1]])))
        ->toThrow(RuntimeException::class, 'DB destroy error');
});

test('Logger::setLogger accepts null to reset underlying logger', function () {
    $logger = new Logger;
    $mock = Mockery::mock(LoggerInterface::class);

    $logger->setLogger($mock);
    expect($logger->getLogger())->toBe($mock);

    $logger->setLogger(null);
    expect($logger->getLogger())->not->toBe($mock)
        ->and($logger->getLogger())->toBeInstanceOf(LoggerInterface::class);
});

test('Logger handles logging safely in non-HTTP or CLI contexts', function () {
    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->once()
        ->with('info', 'CLI background task finished', Mockery::on(function (array $context) {
            return array_key_exists('admin_user_id', $context)
                && array_key_exists('admin_user', $context)
                && array_key_exists('ip', $context)
                && array_key_exists('method', $context)
                && array_key_exists('path', $context);
        }));

    $logger = new Logger($mock);
    $logger->info('CLI background task finished');
});

test('Admin facade resolves logger and delegates logging correctly', function () {
    $mock = Mockery::mock(LoggerInterface::class);
    $mock->shouldReceive('log')
        ->once()
        ->with('info', 'Facade info message', Mockery::type('array'));

    \BlatUI\Admin\Facades\Admin::logger()->setLogger($mock);

    \BlatUI\Admin\Facades\Admin::log('info', 'Facade info message');
});
