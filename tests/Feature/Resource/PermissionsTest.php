<?php

declare(strict_types=1);

use BlatUI\Admin\Http\Controllers\Resources\PermissionsController;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Permission;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the permissions index renders a grid', function () {
    $this->get('/admin/auth/permissions')->assertOk()->assertSee('auth');
});

test('a permission can be created', function () {
    $this->post('/admin/auth/permissions', [
        'name' => 'Test Permission',
        'slug' => 'test-permission',
        'http_method' => 'GET,POST',
        'http_path' => '/test',
        'order' => 1,
        'parent_id' => 0,
    ])->assertRedirect('/admin/auth/permissions');

    expect(Permission::query()->where('slug', 'test-permission')->exists())->toBeTrue();
});

test('the edit form excludes the permission from its own parent options', function () {
    $permission = Permission::query()->create([
        'name' => 'Self',
        'slug' => 'self',
        'parent_id' => 0,
    ]);

    $child = Permission::query()->create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $permission->id,
    ]);

    // Create a dummy instance to test the protected method
    $controller = new class extends PermissionsController
    {
        public function getParentOptions($excludeId)
        {
            return $this->parentOptions($excludeId);
        }
    };

    $options = $controller->getParentOptions($permission->id);

    expect(array_key_exists($permission->id, $options))->toBeFalse()
        ->and(array_key_exists($child->id, $options))->toBeTrue();
});

test('a permission can be updated and deleted', function () {
    $permission = Permission::query()->create([
        'name' => 'To Update',
        'slug' => 'to-update',
        'parent_id' => 0,
    ]);

    $this->put("/admin/auth/permissions/{$permission->id}", [
        'name' => 'Updated Name',
        'slug' => 'updated-slug',
    ])->assertRedirect('/admin/auth/permissions');

    expect($permission->fresh()->name)->toBe('Updated Name')
        ->and($permission->fresh()->slug)->toBe('updated-slug');

    $this->deleteJson("/admin/auth/permissions/{$permission->id}")->assertOk();

    expect(Permission::query()->whereKey($permission->id)->exists())->toBeFalse();
});

test('validates slug format and uniqueness', function () {
    $this->post('/admin/auth/permissions', [
        'name' => 'Test',
        'slug' => 'not a slug',
    ])->assertSessionHasErrors('slug');

    $existing = Permission::query()->firstOrFail();

    $this->post('/admin/auth/permissions', [
        'name' => 'Test',
        'slug' => $existing->slug,
    ])->assertSessionHasErrors('slug');
});
