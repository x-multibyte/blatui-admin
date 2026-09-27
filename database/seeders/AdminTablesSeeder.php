<?php

declare(strict_types=1);

namespace Database\Seeders;

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or update default Super Administrator user
        /** @var Administrator $admin */
        $admin = Administrator::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin'),
                'name' => 'Administrator',
            ],
        );

        // 2. Create or update Administrator role
        /** @var Role $role */
        $role = Role::firstOrCreate(
            ['slug' => 'administrator'],
            [
                'name' => 'Administrator',
            ],
        );

        // Assign role to admin user if not already attached
        if (! $admin->roles()->where('slug', 'administrator')->exists()) {
            $admin->roles()->attach($role);
        }

        // 3. Create default permissions
        $permissions = [
            [
                'name' => 'Auth management',
                'slug' => 'auth-management',
                'http_method' => '',
                'http_path' => '',
                'parent_id' => 0,
                'order' => 1,
            ],
            [
                'name' => 'Users',
                'slug' => 'users',
                'http_method' => '',
                'http_path' => '/auth/users*',
                'parent_id' => 1,
                'order' => 2,
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
                'http_method' => '',
                'http_path' => '/auth/roles*',
                'parent_id' => 1,
                'order' => 3,
            ],
            [
                'name' => 'Permissions',
                'slug' => 'permissions',
                'http_method' => '',
                'http_path' => '/auth/permissions*',
                'parent_id' => 1,
                'order' => 4,
            ],
            [
                'name' => 'Menu',
                'slug' => 'menu',
                'http_method' => '',
                'http_path' => '/auth/menu*',
                'parent_id' => 1,
                'order' => 5,
            ],
            [
                'name' => 'Operation Log',
                'slug' => 'operation-log',
                'http_method' => '',
                'http_path' => '/auth/logs*',
                'parent_id' => 1,
                'order' => 6,
            ],
        ];

        foreach ($permissions as $item) {
            $perm = Permission::firstOrCreate(['slug' => $item['slug']], $item);

            if (! $role->permissions()->where('slug', $item['slug'])->exists()) {
                $role->permissions()->attach($perm);
            }
        }

        // 4. Create default menu items
        $dashboardMenu = Menu::firstOrCreate(
            ['uri' => '/'],
            [
                'parent_id' => 0,
                'order' => 1,
                'title' => 'Dashboard',
                'icon' => 'lucide-layout-dashboard',
                'show' => 1,
            ],
        );

        $adminMenu = Menu::firstOrCreate(
            ['title' => 'Admin', 'parent_id' => 0],
            [
                'order' => 2,
                'icon' => 'lucide-settings',
                'uri' => '',
                'show' => 1,
            ],
        );

        $subMenus = [
            [
                'parent_id' => $adminMenu->id,
                'order' => 3,
                'title' => 'Users',
                'icon' => 'lucide-users',
                'uri' => 'auth/users',
                'show' => 1,
            ],
            [
                'parent_id' => $adminMenu->id,
                'order' => 4,
                'title' => 'Roles',
                'icon' => 'lucide-shield',
                'uri' => 'auth/roles',
                'show' => 1,
            ],
            [
                'parent_id' => $adminMenu->id,
                'order' => 5,
                'title' => 'Permission',
                'icon' => 'lucide-key',
                'uri' => 'auth/permissions',
                'show' => 1,
            ],
            [
                'parent_id' => $adminMenu->id,
                'order' => 6,
                'title' => 'Menu',
                'icon' => 'lucide-menu',
                'uri' => 'auth/menu',
                'show' => 1,
            ],
            [
                'parent_id' => $adminMenu->id,
                'order' => 7,
                'title' => 'Operation Log',
                'icon' => 'lucide-clipboard-list',
                'uri' => 'auth/logs',
                'show' => 1,
            ],
        ];

        foreach ($subMenus as $menuData) {
            Menu::firstOrCreate(['uri' => $menuData['uri']], $menuData);
        }
    }
}
