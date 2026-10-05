<?php

declare(strict_types=1);
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\OperationLog;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;

return [
    'name' => 'BlatUI Admin',
    'title' => 'BlatUI 管理后台',
    'logo' => '<b>BlatUI</b> Admin',

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix' => 'admin',
        'domain' => null,
        'middleware' => ['web'],
        'namespace' => 'App\\Admin\\Controllers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication & Guards
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'guard' => 'admin',
        'guards' => [
            'admin' => [
                'driver' => 'session',
                'provider' => 'admin',
            ],
        ],
        'providers' => [
            'admin' => [
                'driver' => 'eloquent',
                'model' => Administrator::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Settings
    |--------------------------------------------------------------------------
    |
    | Global RBAC permission configuration. When enabled, requests to admin
    | routes must match an assigned permission rule unless whitelisted.
    |
    */
    'permission' => [
        'enable' => true,
        'except' => [
            '/',
            'auth/login',
            'auth/logout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Controllers
    |--------------------------------------------------------------------------
    |
    | Each key becomes an `auth/{key}` route group below the admin prefix. The
    | URL segment is the sidebar URI, which differs from the key for the
    | administrators and menus resources.
    |
    | Point a key at your own controller to replace a bundled resource page
    | without forking the package. A key whose class does not exist falls back
    | to the package-bundled controller of the same name.
    |
    */
    'resources' => [
        'administrators' => 'BlatUI\Admin\Http\Controllers\Resources\AdministratorsController',
        'roles' => 'BlatUI\Admin\Http\Controllers\Resources\RolesController',
        'permissions' => 'BlatUI\Admin\Http\Controllers\Resources\PermissionsController',
        'menus' => 'BlatUI\Admin\Http\Controllers\Resources\MenusController',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database & Models
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => '',
        'users_table' => 'admin_users',
        'users_model' => Administrator::class,
        'roles_table' => 'admin_roles',
        'roles_model' => Role::class,
        'permissions_table' => 'admin_permissions',
        'permissions_model' => Permission::class,
        'menu_table' => 'admin_menu',
        'menu_model' => Menu::class,
        'operation_log_table' => 'admin_operation_log',
        'operation_log_model' => OperationLog::class,
        'role_users_table' => 'admin_role_users',
        'role_permissions_table' => 'admin_role_permissions',
        'role_menu_table' => 'admin_role_menu',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Disks
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'disk' => 'public',
        'directory' => [
            'image' => 'admin/images',
            'file' => 'admin/files',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout & Themes
    |--------------------------------------------------------------------------
    */
    'layout' => [
        'color' => 'default',
        'dark_mode_switch' => true,
        'sidebar_collapsed' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Logging Settings
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enable' => env('ADMIN_LOGGING_ENABLE', true),
        'channel' => env('ADMIN_LOG_CHANNEL', null),
        'level' => env('ADMIN_LOG_LEVEL', 'debug'),
    ],
];
