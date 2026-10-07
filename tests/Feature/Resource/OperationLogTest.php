<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Feature\Resource;

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\OperationLog;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the operation logs index renders a grid with log entries', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/users',
        'method' => 'GET',
        'ip' => '192.168.1.100',
        'input' => json_encode(['page' => 1], JSON_THROW_ON_ERROR),
    ]);

    $response = $this->get('/admin/auth/logs');

    $response->assertOk()
        ->assertSee('admin/auth/users')
        ->assertSee('192.168.1.100')
        ->assertSee('GET');
});

test('the operation logs grid disables create button', function () {
    $response = $this->get('/admin/auth/logs');

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->not->toContain('/admin/auth/logs/create');
});

test('operation logs cannot be created or edited via form endpoints', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $log = OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/users',
        'method' => 'GET',
        'ip' => '127.0.0.1',
        'input' => '{}',
    ]);

    $this->get('/admin/auth/logs/create')->assertNotFound();
    $this->post('/admin/auth/logs', ['path' => 'fake'])->assertNotFound();
    $this->get("/admin/auth/logs/{$log->id}/edit")->assertNotFound();
    $this->put("/admin/auth/logs/{$log->id}", ['path' => 'fake'])->assertNotFound();
});

test('an operation log can be deleted', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $log = OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/users',
        'method' => 'DELETE',
        'ip' => '127.0.0.1',
        'input' => '{}',
    ]);

    $this->deleteJson("/admin/auth/logs/{$log->id}")
        ->assertOk()
        ->assertJson(['status' => true]);

    expect(OperationLog::query()->find($log->id))->toBeNull();
});

test('operation logs can be batch deleted', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $log1 = OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/roles',
        'method' => 'POST',
        'ip' => '127.0.0.1',
        'input' => '{}',
    ]);

    $log2 = OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/roles/1',
        'method' => 'DELETE',
        'ip' => '127.0.0.1',
        'input' => '{}',
    ]);

    $this->deleteJson('/admin/auth/logs/batch-delete', [
        'ids' => [$log1->id, $log2->id],
    ])->assertOk()->assertJson(['status' => true]);

    expect(OperationLog::query()->whereIn('id', [$log1->id, $log2->id])->count())->toBe(0);
});

test('operation logs filter by method, path, and user_id', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/users/target',
        'method' => 'POST',
        'ip' => '10.0.0.1',
        'input' => '{}',
    ]);

    OperationLog::query()->create([
        'user_id' => $admin->id,
        'path' => 'admin/auth/other',
        'method' => 'GET',
        'ip' => '10.0.0.2',
        'input' => '{}',
    ]);

    $response = $this->get('/admin/auth/logs?method=POST');
    $response->assertOk()
        ->assertSee('admin/auth/users/target')
        ->assertDontSee('admin/auth/other');
});
