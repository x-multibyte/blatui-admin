<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the roles index renders a grid', function () {
    $this->get('/admin/auth/roles')->assertOk()->assertSee('Administrator');
});

test('a role can be created with permissions and menus', function () {
    $permission = Permission::query()->where('slug', 'users')->firstOrFail();
    $menu = Menu::query()->where('uri', 'auth/users')->firstOrFail();

    $this->post('/admin/auth/roles', [
        'name' => 'Editor', 'slug' => 'editor',
        'permissions' => [$permission->id],
        'menus' => [$menu->id],
    ])->assertRedirect('/admin/auth/roles');

    $role = Role::query()->where('slug', 'editor')->firstOrFail();

    expect($role->permissions->pluck('id')->all())->toBe([$permission->id])
        ->and($role->menus->pluck('id')->all())->toBe([$menu->id]);
});

test('updating a role removes deselected permissions', function () {
    $users = Permission::query()->where('slug', 'users')->firstOrFail();
    $roles = Permission::query()->where('slug', 'roles')->firstOrFail();

    $response = $this->withoutExceptionHandling()->post('/admin/auth/roles', [
        'name' => 'Editor', 'slug' => 'editor',
        'permissions' => [$users->id, $roles->id],
    ]);

    $role = Role::query()->where('slug', 'editor')->firstOrFail();

    $this->put("/admin/auth/roles/{$role->id}", [
        'name' => 'Editor', 'slug' => 'editor', 'permissions' => [$users->id],
    ]);

    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$users->id]);
});

test('permission options are indented by hierarchy', function () {
    Permission::query()->updateOrCreate(
        ['slug' => 'auth-management'],
        ['name' => 'Auth management', 'parent_id' => 0, 'order' => 1],
    );
    $parent = Permission::query()->where('slug', 'users')->firstOrFail();
    $parent->update(['parent_id' => Permission::query()->where('slug', 'auth-management')->firstOrFail()->id]);

    $html = $this->get('/admin/auth/roles/create')->assertOk()->getContent();

    expect($html)->toContain('Users');
});

test('validates slug format and uniqueness', function () {
    $this->post('/admin/auth/roles', ['name' => 'Editor', 'slug' => 'not a slug'])
        ->assertSessionHasErrors('slug');

    $this->post('/admin/auth/roles', ['name' => 'Editor', 'slug' => 'administrator'])
        ->assertSessionHasErrors('slug');
});

test('refuses to delete the administrator role', function () {
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    $this->deleteJson("/admin/auth/roles/{$role->id}")->assertForbidden();

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('the administrator role delete refusal is localized', function () {
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    app()->setLocale('zh_CN');

    $this->deleteJson("/admin/auth/roles/{$role->id}")
        ->assertForbidden()
        ->assertJsonPath('message', '内置管理员角色不能删除。');
});

test('a role can be deleted', function () {
    $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

    $this->deleteJson("/admin/auth/roles/{$role->id}")->assertOk();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});
