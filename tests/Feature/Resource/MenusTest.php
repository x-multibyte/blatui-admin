<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('index renders grid', function () {
    $this->get('/admin/auth/menu')->assertOk()->assertSee('Dashboard');
});

test('a menu can be created with a role assignment', function () {
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    $this->post('/admin/auth/menu', [
        'title' => 'New Menu',
        'icon' => 'lucide-users',
        'uri' => 'new-uri',
        'order' => 10,
        'show' => '1',
        'parent_id' => '0',
        'roles' => [$role->id],
    ])->assertRedirect('/admin/auth/menu');

    $menu = Menu::query()->where('title', 'New Menu')->firstOrFail();

    expect($menu->roles->pluck('id')->all())->toBe([$role->id]);
});

test('a hidden menu defaults to hidden when the switch is off', function () {
    $this->post('/admin/auth/menu', [
        'title' => 'Hidden Menu',
        'icon' => '',
        'uri' => 'hidden',
        'order' => 0,
        // switch off, so 'show' might not be sent. prepareDataForSave should set it to 0
        'parent_id' => '0',
    ])->assertRedirect('/admin/auth/menu');

    $menu = Menu::query()->where('title', 'Hidden Menu')->firstOrFail();

    expect((int) $menu->show)->toBe(0);
});

test('a menu can be updated and deleted', function () {
    $menu = Menu::query()->create([
        'title' => 'Temp Menu',
        'order' => 1,
        'show' => 1,
        'parent_id' => 0,
    ]);

    $this->put("/admin/auth/menu/{$menu->id}", [
        'title' => 'Updated Temp Menu',
        'show' => '1',
        'parent_id' => '0',
    ])->assertRedirect('/admin/auth/menu');

    expect($menu->fresh()->title)->toBe('Updated Temp Menu');

    $this->deleteJson("/admin/auth/menu/{$menu->id}")->assertOk();

    expect(Menu::query()->whereKey($menu->id)->exists())->toBeFalse();
});

test('menu parent options exclude the record itself', function () {
    $menu = Menu::query()->where('title', 'Dashboard')->firstOrFail();

    $html = $this->get("/admin/auth/menu/{$menu->id}/edit")->assertOk()->getContent();

    expect($html)->not->toContain('<option value="'.$menu->id.'">Dashboard</option>');
});
