---
type: concept
title: Installation, Publishing, and Operational Workflows
description: Detailed operational workflows for installing BlatUI Admin via admin:install, publishing vendor assets, configuring environment overrides, and administering system users.
tags: [workflows, operations, installation, publishing, seeders, artisan]
verified:
  - by: openwiki/0.6.0
    at: 2026-09-27T09:35:07.392Z
sources:
  - id: openwiki-source-ec5c5d87d1eb6e4d270dea98
    resource: repo://database/seeders/AdminTablesSeeder.php
  - id: openwiki-source-7b34f9c094dc790aa64c1298
    resource: repo://src/AdminServiceProvider.php
  - id: openwiki-source-978d47f39be3c14f87fd267d
    resource: repo://src/Console/Commands/InstallCommand.php
generated: { by: "antigravity", at: "2026-09-27T09:35:07.392Z" }
---

# Installation, Publishing, and Operational Workflows

## Operational Overview

Integrating BlatUI Admin into a Laravel application follows a turnkey, automated workflow designed to provision database tables, seed foundational RBAC permissions, create necessary storage directories, and publish extension points.

```
+-------------------------------------------------------------+
|                      composer require                       |
|                 x-multibyte/blatui-admin                    |
+-------------------------------------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                     php artisan admin:install               |
|                                                             |
| 1. Execute database migrations (8 tables)                   |
| 2. Run AdminTablesSeeder (if admin count == 0)              |
| 3. Publish config (blatui-admin-config)                     |
| 4. Publish routes (blatui-admin-routes)                     |
| 5. Create storage upload directory                          |
+-------------------------------------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|                 Access http://host/admin                    |
|             (Login: admin / Password: admin)                |
+-------------------------------------------------------------+
```

---

## The Automated Installation Workflow (`admin:install`)

The primary entry point for setting up the package is the `admin:install` Artisan command implemented by `BlatUI\Admin\Console\Commands\InstallCommand`.

### Execution Steps:
1. **Migration Execution**:
   - Triggers `php artisan migrate` to provision the 8 core relational tables: `admin_users`, `admin_roles`, `admin_permissions`, `admin_menu`, `admin_operation_log`, and the pivot tables (`admin_role_users`, `admin_role_permissions`, `admin_role_menu`).
2. **Idempotent Data Seeding**:
   - Checks `config('blatui-admin.database.users_model')::count()`.
   - If empty, runs `Database\Seeders\AdminTablesSeeder` to create the default administrator, root role, system permissions, and initial navigation tree.
3. **Configuration & Route Publishing**:
   - Calls `vendor:publish --tag=blatui-admin-config` to generate `config/blatui-admin.php`.
   - Calls `vendor:publish --tag=blatui-admin-routes` to generate `routes/admin.php` for custom application routes.
4. **Storage Directory Provisioning**:
   - Inspects `config('blatui-admin.upload.disk', 'public')`.
   - Ensures the public upload directory `storage/app/public/admin/images` exists with `0755` permissions.

```bash
php artisan admin:install
```

---

## Default Administrative Credentials

Upon successful execution of `admin:install`, default credentials are created:

| Attribute | Default Value | Notes |
| :--- | :--- | :--- |
| **Username** | `admin` | Unique login identifier |
| **Password** | `admin` | Hashed via `Hash::make('admin')` |
| **Name** | `Administrator` | Display name in headers |
| **Role** | `administrator` | Super Administrator role granting global access |

> [!IMPORTANT]
> Change the default superadmin password immediately in production environments.

---

## Granular Resource Publishing

Developers can selectively export package resources into the host repository using dedicated publish tags:

```bash
# Publish configuration only
php artisan vendor:publish --tag=blatui-admin-config

# Publish migrations to modify schema before migrating
php artisan vendor:publish --tag=blatui-admin-migrations

# Publish initial seeders for data customization
php artisan vendor:publish --tag=blatui-admin-seeders

# Publish Blade views and UI components
php artisan vendor:publish --tag=blatui-admin-views

# Publish multi-language translation files
php artisan vendor:publish --tag=blatui-admin-lang

# Publish public assets and scripts
php artisan vendor:publish --tag=blatui-admin-assets

# Publish custom administrative route definitions
php artisan vendor:publish --tag=blatui-admin-routes

# Export all resources at once
php artisan vendor:publish --tag=blatui-admin
```

---

## Host Route Customization (`routes/admin.php`)

Publishing `blatui-admin-routes` writes `routes/admin.php` to the application root. Developers register their custom administrative controllers directly into this file:

```php
use App\Admin\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::resource('users', UserController::class);
```

These routes are automatically prefixed and wrapped with the admin authentication middleware configured in `config/blatui-admin.php`.

---

## Operational Maintenance

### 1. Storage Symlink
Ensure the public storage disk is symlinked so uploaded avatars and grid images are publicly accessible:
```bash
php artisan storage:link
```

### 2. Database Reset & Re-seeding
To reset the administrative portal during local development:
```bash
php artisan migrate:fresh --seed --seeder=AdminTablesSeeder
```
