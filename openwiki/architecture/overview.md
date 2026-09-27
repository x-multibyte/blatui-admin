---
type: concept
title: Architecture Overview and Service Lifecycle
description: Core package architecture, service provider registration, configuration merging, authentication guard setup, route routing, and vendor publish tags in BlatUI Admin.
tags: [architecture, service-provider, configuration, routing, lifecycle, publishing]
verified:
  - by: openwiki/0.6.0
    at: 2026-09-27T09:35:07.392Z
sources:
  - id: openwiki-source-46a091fc7ac7f25ec7bc7e85
    resource: repo://config/blatui-admin.php
  - id: openwiki-source-c47cf09e347a62fb45dc4100
    resource: repo://routes/blatui-admin.php
  - id: openwiki-source-753dde7fb0b7e13773c066f6
    resource: repo://src/Admin.php
  - id: openwiki-source-7b34f9c094dc790aa64c1298
    resource: repo://src/AdminServiceProvider.php
generated: { by: "antigravity", at: "2026-09-27T09:35:07.392Z" }
---

# Architecture Overview and Service Lifecycle

## Architectural Overview

`x-multibyte/blatui-admin` is a modern administration panel package designed for Laravel applications. It synthesizes a declarative, fluent PHP builder DSL with a modern BLAT frontend stack (Blade, Laravel, Alpine.js, and Tailwind CSS v4).

The package operates as a standalone administrative layer that plugs seamlessly into host Laravel applications without altering host authentication schemes, user models, or frontend asset build pipelines.

```
+-------------------------------------------------------------+
|                      Host Application                       |
+-------------------------------------------------------------+
                               |
                               v
+-------------------------------------------------------------+
|              BlatUI\Admin\AdminServiceProvider              |
+-------------------------------------------------------------+
         |                                           |
         v (register)                                v (boot)
+-------------------------+             +-------------------------+
| - mergeConfigFrom       |             | - loadRoutesFrom        |
| - loadAdminAuthConfig   |             | - loadViewsFrom         |
| - singleton(Admin::class|             | - loadTranslationsFrom  |
+-------------------------+             | - loadMigrationsFrom    |
                                        | - publishes (8 tags)    |
                                        | - commands (admin:*)    |
                                        +-------------------------+
```

---

## Service Provider Lifecycle (`AdminServiceProvider`)

The package lifecycle is managed by `BlatUI\Admin\AdminServiceProvider`.

### Registration Phase (`register()`)
1. **Configuration Merging**: `mergeConfigFrom(__DIR__.'/../config/blatui-admin.php', 'blatui-admin')` loads package defaults while allowing host overrides in `config/blatui-admin.php`.
2. **Authentication Injection**: `loadAdminAuthConfig()` flattens the package's `auth` configuration array via `Arr::dot(..., 'auth.')` and merges it directly into Laravel's root authentication configuration (`auth.guards.admin` and `auth.providers.admin`). This provisions the `admin` guard dynamically without manual edits to `config/auth.php`.
3. **Singleton Registration**: Binds `BlatUI\Admin\Admin::class` as an application container singleton.

### Bootstrapping Phase (`boot()`)
1. **Routes**: Registers routes defined in `routes/blatui-admin.php` via `loadRoutesFrom()`.
2. **Views**: Registers Blade view namespace `blatui-admin::` pointing to `resources/views`.
3. **Translations**: Loads language catalogues from `lang/` under the `blatui-admin::` namespace.
4. **Migrations**: Discovers database migrations from `database/migrations`.
5. **Console Commands**: Registers console commands `admin:install` and `admin:make` when running in CLI mode.

---

## Configuration Pipeline (`config/blatui-admin.php`)

The package exposes explicit configuration dimensions:
- **Identity & Branding**: `name`, `title`, and `logo` displayed across administrative headers and documents.
- **Routing**: `route.prefix` (defaults to `admin`), `route.domain`, `route.middleware` (`['web']`), and default controller namespace.
- **Authentication**: `auth.guard` (`admin`), custom guard drivers, and user provider model bindings (`Administrator::class`).
- **Database Table Mapping**: Configurable table identifiers (`admin_users`, `admin_roles`, `admin_permissions`, `admin_menu`, `admin_operation_log`, etc.) allowing custom table prefixes or names.
- **Storage & Uploads**: Target disk (`public`) and upload directories for administrative assets.
- **Layout Tokens**: Dark mode toggles, color palette defaults, and sidebar collapse states.

---

## Service Gateway & Facade (`Admin`)

`BlatUI\Admin\Admin` serves as the public API facade and contextual gateway for the active admin session:

```php
use BlatUI\Admin\Facades\Admin;

// Inspect authenticated administrator
$user = Admin::user();
$userId = Admin::id();

// Access or configure top navbar widgets
Admin::navbar(function (Navbar $navbar) {
    $navbar->right(new NotificationBell());
});

// Generate prefixed URLs
$url = Admin::url('auth/users'); // Resolves to /admin/auth/users
```

---

## Route Architecture & Middleware Stack

Routes are encapsulated within `routes/blatui-admin.php` and grouped dynamically based on `config('blatui-admin.route')`:
- **Unauthenticated / Guest Routes**:
  - `GET /admin/auth/login`: Administrative login interface (`AuthController::getLogin`).
  - `POST /admin/auth/login`: Credential validation and session initialization (`AuthController::postLogin`).
  - `POST /admin/auth/logout`: Administrative session termination.
- **Protected Administrative Routes**:
  - Guarded by `BlatUI\Admin\Http\Middleware\Authenticate`.
  - Redirects unauthenticated requests to the administrative login route rather than standard host application login pages.
  - `GET /admin`: Administrative dashboard landing (`DashboardController::index`).

---

## Resource Publishing Tags

The package registers eight granular publish tags to facilitate customization and host scaffolding:

| Publish Tag | Destination Target | Description |
| :--- | :--- | :--- |
| **`blatui-admin`** | *(All Targets)* | Complete export of all package resources. |
| **`blatui-admin-config`** | `config/blatui-admin.php` | Main configuration file. |
| **`blatui-admin-views`** | `resources/views/vendor/blatui-admin` | Blade views, layouts, and UI components. |
| **`blatui-admin-lang`** | `lang/vendor/blatui-admin` | Translation strings (`en`, `zh_CN`). |
| **`blatui-admin-assets`** | `public/vendor/blatui-admin` | Static assets and client scripts. |
| **`blatui-admin-seeders`** | `database/seeders` | Default database seeders. |
| **`blatui-admin-routes`** | `routes/admin.php` | Host administrative route file. |
| **`blatui-admin-migrations`** | `database/migrations` | Relational table migration files. |
