# BlatUI Admin Foundation Implementation Plan

> **Status: COMPLETE (verified 2026-09-28).** The planned deliverables are present in the repository: a single `create_admin_tables` migration creating the core tables, `database/seeders/AdminTablesSeeder.php`, and `routes/blatui-admin.php`. Retained for reference; no further work is pending here.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the robust foundation for `x-multibyte/blatui-admin`: configuration, migrations (7 core tables), database seeders, route loading, resource publishers (8 tags), core models (Administrator, Role, Permission, Menu, OperationLog), and the `admin:install` command with complete Pest test coverage.

**Architecture:** Port battle-tested schema and model logic from `/root/projects/dcat-admin` into the `BlatUI\Admin` namespace. Wire all capabilities through explicit Laravel-native APIs in `AdminServiceProvider` following the `package-scaffold` conventions. Provide executable Pest feature tests verifying config merging, migrations, seeders, route discovery, resource publishing, and console commands with Orchestra Testbench.

**Tech Stack:** PHP ^8.3, Laravel ^12.0 || ^13.0, Orchestra Testbench, Pest 4/5, PHPStan/Larastan, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-09-27-blatui-admin-design.md`

## Global Constraints

- Package namespace must be strictly `BlatUI\Admin`.
- Artisan command signatures must strictly use `admin:xxx` (e.g. `admin:install`).
- Publish tags must strictly follow `blatui-admin-*` naming conventions with `blatui-admin` as master tag.
- Strictly adhere to PHP 8.3+ with `declare(strict_types=1);` and 100% type coverage.
- Code style must pass `vendor/bin/pint --test` and analysis must pass `vendor/bin/phpstan analyse`.

## Review Focus

- **Fresh SQLite In-Memory Migrations**: Verify all 7 core tables migrate cleanly without SQLite syntax or index name collisions.
- **Seeder Idempotency**: Running `AdminTablesSeeder` multiple times must not crash with unique constraint errors.
- **Custom Table Prefix/Names in Config**: Models and migrations must respect table names defined in `config('blatui-admin.database')`.
- **Publisher Tag Completeness**: Every tag (`blatui-admin-config`, `migrations`, `seeders`, `routes`, `views`, `lang`, `assets`) must publish exactly to its expected destination.
- **Route Isolation & Prefixing**: Default admin routes must be bound to the configured prefix (default `admin`) and middleware stack.

---

### Task 1: Complete Configuration File (`config/blatui-admin.php`)

**Files:**
- Modify: `config/blatui-admin.php`
- Test: `tests/Feature/ConfigTest.php`

**Interfaces:**
- Consumes: None
- Produces: `config('blatui-admin')` containing `route`, `auth`, `database`, `upload`, `layout` arrays.

- [ ] **Step 1: Write the failing test for configuration merging**

```php
// tests/Feature/ConfigTest.php
test('it merges default blatui-admin configuration', function () {
    expect(config('blatui-admin.route.prefix'))->toBe('admin')
        ->and(config('blatui-admin.auth.guard'))->toBe('admin')
        ->and(config('blatui-admin.database.users_table'))->toBe('admin_users')
        ->and(config('blatui-admin.database.roles_table'))->toBe('admin_roles')
        ->and(config('blatui-admin.database.permissions_table'))->toBe('admin_permissions')
        ->and(config('blatui-admin.database.menu_table'))->toBe('admin_menu')
        ->and(config('blatui-admin.database.operation_log_table'))->toBe('admin_operation_log');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/ConfigTest.php`
Expected: FAIL due to missing config keys.

- [ ] **Step 3: Update `config/blatui-admin.php` with complete configuration schema**

Port configuration from spec section 3.1: title, name, logo, route (prefix, domain, middleware, namespace), auth (guard, guards, providers), database (connection, tables, models), upload (disk, directories), layout (dark_mode_switch, color).

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/ConfigTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/blatui-admin.php tests/Feature/ConfigTest.php
git commit -m "feat(config): provide comprehensive blatui-admin configuration"
```

---

### Task 2: Core Models and Contracts (`Administrator`, `Role`, `Permission`, `Menu`, `OperationLog`)

**Files:**
- Create: `src/Contracts/Tree.php`
- Create: `src/Traits/HasPermissions.php`
- Create: `src/Traits/ModelTree.php`
- Create: `src/Models/Administrator.php`
- Create: `src/Models/Role.php`
- Create: `src/Models/Permission.php`
- Create: `src/Models/Menu.php`
- Create: `src/Models/OperationLog.php`
- Test: `tests/Unit/ModelsTest.php`

**Interfaces:**
- Consumes: `config('blatui-admin.database.*')`
- Produces: Eloquent Models implementing auth and RBAC relationships.

- [ ] **Step 1: Write the failing test for Models**

```php
// tests/Unit/ModelsTest.php
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\OperationLog;

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ModelsTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Traits and Models**

1. Create `src/Traits/HasPermissions.php` and `src/Traits/ModelTree.php`.
2. Create `src/Models/Administrator.php` extending `Illuminate\Foundation\Auth\User` using `HasPermissions`.
3. Create `src/Models/Role.php`, `Permission.php`, `Menu.php` (using `ModelTree`), `OperationLog.php`.
4. Ensure each model resolves its table from `config('blatui-admin.database.<type>_table', default)` in `getTable()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ModelsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Contracts/ src/Traits/ src/Models/ tests/Unit/ModelsTest.php
git commit -m "feat(models): implement core administrator and RBAC models"
```

---

### Task 3: Core Database Migrations (7 Core Tables)

**Files:**
- Create: `database/migrations/2026_09_27_000000_create_admin_tables.php`
- Test: `tests/Feature/MigrationTest.php`

**Interfaces:**
- Consumes: `config('blatui-admin.database')`
- Produces: SQLite/MySQL tables: `admin_users`, `admin_roles`, `admin_permissions`, `admin_menu`, `admin_role_users`, `admin_role_permissions`, `admin_role_menu`, `admin_operation_log`.

- [ ] **Step 1: Write the failing test for migrations**

```php
// tests/Feature/MigrationTest.php
use Illuminate\Support\Facades\Schema;

test('it runs admin migrations and creates all 7 tables', function () {
    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('admin_users'))->toBeTrue()
        ->and(Schema::hasTable('admin_roles'))->toBeTrue()
        ->and(Schema::hasTable('admin_permissions'))->toBeTrue()
        ->and(Schema::hasTable('admin_menu'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_users'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_permissions'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_menu'))->toBeTrue()
        ->and(Schema::hasTable('admin_operation_log'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/MigrationTest.php`
Expected: FAIL because tables do not exist.

- [ ] **Step 3: Implement `create_admin_tables.php`**

Port schema from `/root/projects/dcat-admin/database/migrations/2016_01_04_173148_create_admin_tables.php`:
Read table names dynamically from `config('blatui-admin.database')`.
Define fields:
- `users`: id, username, password, name, avatar, remember_token, timestamps.
- `roles`: id, name, slug, timestamps.
- `permissions`: id, name, slug, http_method, http_path, order, parent_id, timestamps.
- `menu`: id, parent_id, order, title, icon, uri, extension, show, timestamps.
- pivot tables: `role_users`, `role_permissions`, `role_menu`.
- `operation_log`: id, user_id, path, method, ip, input, timestamps.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/MigrationTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ tests/Feature/MigrationTest.php
git commit -m "feat(database): add migration for 7 core admin tables"
```

---

### Task 4: Database Seeders (`AdminTablesSeeder`)

**Files:**
- Create: `database/seeders/AdminTablesSeeder.php`
- Test: `tests/Feature/SeederTest.php`

**Interfaces:**
- Consumes: Models from `BlatUI\Admin\Models\*` and tables from `database/migrations`
- Produces: Initial super admin user (`admin` / `admin`), `Administrator` role, root permissions, and core menus.

- [ ] **Step 1: Write the failing test for seeding**

```php
// tests/Feature/SeederTest.php
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Menu;
use Database\Seeders\AdminTablesSeeder;

test('it seeds default admin user, role, permissions and menu tree idempotently', function () {
    $this->artisan('migrate')->assertSuccessful();

    $this->seed(AdminTablesSeeder::class);

    expect(Administrator::where('username', 'admin')->exists())->toBeTrue()
        ->and(Role::where('slug', 'administrator')->exists())->toBeTrue()
        ->and(Permission::where('slug', '*')->exists())->toBeTrue()
        ->and(Menu::where('title', 'Dashboard')->exists())->toBeTrue();

    // Test idempotency
    $this->seed(AdminTablesSeeder::class);
    expect(Administrator::where('username', 'admin')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/SeederTest.php`
Expected: FAIL with `AdminTablesSeeder` not found.

- [ ] **Step 3: Implement `AdminTablesSeeder.php`**

1. Create `AdminTablesSeeder` under `database/seeders/AdminTablesSeeder.php`.
2. Seed default Administrator (`admin` / `Hash::make('admin')`).
3. Seed `administrator` role and attach to `admin`.
4. Seed `*`, `auth-management` permissions and attach to `administrator` role.
5. Seed default menu items: Dashboard (`/`), Admin, Users, Roles, Permissions, Menu, Logs.
6. Use `firstOrCreate` / `updateOrCreate` for full idempotency.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/SeederTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ tests/Feature/SeederTest.php
git commit -m "feat(seeders): add idempotent AdminTablesSeeder"
```

---

### Task 5: Routing and Core Controllers

**Files:**
- Create: `src/Http/Controllers/AdminController.php` (abstract base controller)
- Create: `src/Http/Controllers/AuthController.php` (login/logout)
- Create: `src/Http/Controllers/DashboardController.php` (admin home)
- Modify: `routes/blatui-admin.php`
- Test: `tests/Feature/RouteTest.php`

**Interfaces:**
- Consumes: `config('blatui-admin.route')`
- Produces: Registered web routes for login, logout, and dashboard under prefix `admin`.

- [ ] **Step 1: Write the failing test for admin routes**

```php
// tests/Feature/RouteTest.php
test('admin routes are registered with configured prefix', function () {
    $response = $this->get('/admin/auth/login');
    $response->assertStatus(200);

    $logoutResponse = $this->post('/admin/auth/logout');
    $logoutResponse->assertRedirect('/admin/auth/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/RouteTest.php`
Expected: FAIL with 404.

- [ ] **Step 3: Implement Controllers and Routes**

1. Implement `AdminController.php` base class.
2. Implement `AuthController.php` handling `getLogin`, `postLogin`, `getLogout`.
3. Implement `DashboardController.php` handling `index`.
4. Update `routes/blatui-admin.php` to define routes wrapped with `config('blatui-admin.route.prefix')` and middleware.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/RouteTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Http/Controllers/ routes/blatui-admin.php tests/Feature/RouteTest.php
git commit -m "feat(routes): register admin auth and dashboard routes"
```

---

### Task 6: Complete `AdminServiceProvider` and 8 Publish Tags

**Files:**
- Modify: `src/AdminServiceProvider.php`
- Test: `tests/Feature/PublishTest.php`

**Interfaces:**
- Consumes: Config, migrations, seeders, routes, views, lang, public assets
- Produces: Publishable resources under tags: `blatui-admin` (all), `blatui-admin-config`, `blatui-admin-migrations`, `blatui-admin-seeders`, `blatui-admin-routes`, `blatui-admin-views`, `blatui-admin-lang`, `blatui-admin-assets`.

- [ ] **Step 1: Write the failing test for publishers**

```php
// tests/Feature/PublishTest.php
test('all 8 blatui-admin publish tags are properly registered', function () {
    $tags = [
        'blatui-admin',
        'blatui-admin-config',
        'blatui-admin-migrations',
        'blatui-admin-seeders',
        'blatui-admin-routes',
        'blatui-admin-views',
        'blatui-admin-lang',
        'blatui-admin-assets',
    ];

    foreach ($tags as $tag) {
        $this->artisan('vendor:publish', ['--tag' => $tag, '--dry-run' => true])
            ->assertSuccessful();
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PublishTest.php`
Expected: FAIL for missing tags (`blatui-admin-seeders`, `blatui-admin-routes`).

- [ ] **Step 3: Wire all publishers in `AdminServiceProvider.php`**

Wire the 8 publish tags inside `if ($this->app->runningInConsole())`:
- config -> `config_path('blatui-admin.php')`
- migrations -> `database_path('migrations')` via `publishesMigrations`
- seeders -> `database_path('seeders')`
- routes -> `base_path('routes/admin.php')`
- views -> `resource_path('views/vendor/blatui-admin')`
- lang -> `lang_path('vendor/blatui-admin')`
- assets -> `public_path('vendor/blatui-admin')`

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/PublishTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/AdminServiceProvider.php tests/Feature/PublishTest.php
git commit -m "feat(provider): wire all 8 publish tags and console loading"
```

---

### Task 7: The `admin:install` Artisan Command

**Files:**
- Create: `src/Console/Commands/InstallCommand.php`
- Modify: `src/AdminServiceProvider.php` (register `InstallCommand`)
- Test: `tests/Feature/InstallCommandTest.php`

**Interfaces:**
- Consumes: `AdminTablesSeeder`, migrations, config publisher
- Produces: `php artisan admin:install` command that initializes database and creates default assets and directories.

- [ ] **Step 1: Write the failing test for `admin:install`**

```php
// tests/Feature/InstallCommandTest.php
use BlatUI\Admin\Models\Administrator;

test('admin:install command migrates database and seeds default admin account', function () {
    $this->artisan('admin:install')
        ->expectsOutputToContain('BlatUI Admin installed successfully.')
        ->assertSuccessful();

    expect(Administrator::where('username', 'admin')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/InstallCommandTest.php`
Expected: FAIL with command "admin:install" does not exist.

- [ ] **Step 3: Implement `InstallCommand.php`**

1. Create `src/Console/Commands/InstallCommand.php` with signature `admin:install`.
2. Execute migration calls (`$this->call('migrate')`).
3. Execute seeder call (`$this->call('db:seed', ['--class' => AdminTablesSeeder::class])`).
4. Publish default config and routes.
5. Create default upload directory (`storage/app/public/admin/images`).
6. Output welcome banner and default login credentials (`admin` / `admin`).
7. Register `InstallCommand::class` in `AdminServiceProvider`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/InstallCommandTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Console/Commands/InstallCommand.php src/AdminServiceProvider.php tests/Feature/InstallCommandTest.php
git commit -m "feat(console): implement admin:install command"
```

---

### Task 8: Full Suite Verification & Quality Gate

**Files:**
- Modify: Any files needing linting/type fixes.
- Test: Full validation suite.

- [ ] **Step 1: Run static analysis**
Run: `composer analyse`
Expected: PASS with 0 errors.

- [ ] **Step 2: Run code formatting**
Run: `composer lint:check`
Expected: PASS with 0 style issues.

- [ ] **Step 3: Run full Pest test suite with 100% type coverage**
Run: `composer test`
Expected: PASS (Analyse, Lint check, Test types 100%, and Test unit all green).

- [ ] **Step 4: Commit**
```bash
git add .
git commit -m "chore: verify full foundation test suite and code quality"
```
