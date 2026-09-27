<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;

test('it seeds default admin user, role, permissions and menu tree idempotently', function () {
    $this->artisan('migrate')->assertSuccessful();

    $this->seed(AdminTablesSeeder::class);

    $admin = Administrator::where('username', 'admin')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->isAdministrator())->toBeTrue()
        ->and(Role::where('slug', 'administrator')->exists())->toBeTrue()
        ->and(Permission::where('slug', 'auth-management')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Dashboard')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Admin')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Users')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Roles')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Permission')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Menu')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Operation Log')->exists())->toBeTrue();

    // Verify idempotency (running twice does not crash or duplicate)
    $this->seed(AdminTablesSeeder::class);
    expect(Administrator::where('username', 'admin')->count())->toBe(1)
        ->and(Role::where('slug', 'administrator')->count())->toBe(1);
});
