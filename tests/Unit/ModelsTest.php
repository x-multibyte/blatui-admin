<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\OperationLog;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;

test('models resolve configured table names dynamically', function () {
    $admin = new Administrator();
    expect($admin->getTable())->toBe('admin_users');

    $role = new Role();
    expect($role->getTable())->toBe('admin_roles');

    $permission = new Permission();
    expect($permission->getTable())->toBe('admin_permissions');

    $menu = new Menu();
    expect($menu->getTable())->toBe('admin_menu');

    $log = new OperationLog();
    expect($log->getTable())->toBe('admin_operation_log');
});

test('models respect custom configured table names', function () {
    config([
        'blatui-admin.database.users_table' => 'my_admin_users',
        'blatui-admin.database.roles_table' => 'my_roles',
        'blatui-admin.database.permissions_table' => 'my_permissions',
        'blatui-admin.database.menu_table' => 'my_menu',
        'blatui-admin.database.operation_log_table' => 'my_logs',
    ]);

    expect((new Administrator())->getTable())->toBe('my_admin_users')
        ->and((new Role())->getTable())->toBe('my_roles')
        ->and((new Permission())->getTable())->toBe('my_permissions')
        ->and((new Menu())->getTable())->toBe('my_menu')
        ->and((new OperationLog())->getTable())->toBe('my_logs');
});
