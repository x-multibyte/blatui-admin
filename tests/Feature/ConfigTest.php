<?php

declare(strict_types=1);

test('it merges default blatui-admin configuration', function () {
    expect(config('blatui-admin.name'))->toBe('BlatUI Admin')
        ->and(config('blatui-admin.route.prefix'))->toBe('admin')
        ->and(config('blatui-admin.auth.guard'))->toBe('admin')
        ->and(config('blatui-admin.database.users_table'))->toBe('admin_users')
        ->and(config('blatui-admin.database.roles_table'))->toBe('admin_roles')
        ->and(config('blatui-admin.database.permissions_table'))->toBe('admin_permissions')
        ->and(config('blatui-admin.database.menu_table'))->toBe('admin_menu')
        ->and(config('blatui-admin.database.operation_log_table'))->toBe('admin_operation_log')
        ->and(config('blatui-admin.upload.disk'))->toBe('public')
        ->and(config('blatui-admin.layout.dark_mode_switch'))->toBeTrue();
});
