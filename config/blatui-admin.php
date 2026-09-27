<?php

declare(strict_types=1);

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
        'prefix' => env('ADMIN_ROUTE_PREFIX', 'admin'),
        'domain' => env('ADMIN_ROUTE_DOMAIN'),
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
                'model' => BlatUI\Admin\Models\Administrator::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database & Models
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => '',
        'users_table' => 'admin_users',
        'users_model' => BlatUI\Admin\Models\Administrator::class,
        'roles_table' => 'admin_roles',
        'roles_model' => BlatUI\Admin\Models\Role::class,
        'permissions_table' => 'admin_permissions',
        'permissions_model' => BlatUI\Admin\Models\Permission::class,
        'menu_table' => 'admin_menu',
        'menu_model' => BlatUI\Admin\Models\Menu::class,
        'operation_log_table' => 'admin_operation_log',
        'operation_log_model' => BlatUI\Admin\Models\OperationLog::class,
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
];
