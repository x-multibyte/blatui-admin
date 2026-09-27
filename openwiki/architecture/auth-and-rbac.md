---
type: concept
title: Authentication, RBAC and Data Models
description: Architecture of administrator authentication, role-based access control, dynamic menu hierarchy, and audit logging in BlatUI Admin.
tags: [architecture, auth, rbac, models, permissions, migrations]
verified:
  - by: openwiki/0.6.0
    at: 2026-09-27T09:35:07.392Z
sources:
  - id: openwiki-source-46a091fc7ac7f25ec7bc7e85
    resource: repo://config/blatui-admin.php
  - id: openwiki-source-60701cf08a7fa866e9c55d2d
    resource: repo://database/migrations/2026_09_27_000000_create_admin_tables.php
  - id: openwiki-source-ec5c5d87d1eb6e4d270dea98
    resource: repo://database/seeders/AdminTablesSeeder.php
  - id: openwiki-source-da736e19ceab7c0451373932
    resource: repo://src/Models/Administrator.php
  - id: openwiki-source-4a81c5342f6a60deccbd0bd2
    resource: repo://src/Models/Menu.php
  - id: openwiki-source-e0a841f7a0693d0cfa7c3570
    resource: repo://src/Models/OperationLog.php
  - id: openwiki-source-35c030a773c2c4ad67295acf
    resource: repo://src/Traits/HasPermissions.php
  - id: openwiki-source-7522a490e9ffeb642d7630a9
    resource: repo://src/Traits/ModelTree.php
generated: { by: "antigravity", at: "2026-09-27T09:35:07.392Z" }
---

# Authentication, RBAC and Data Models

## Architectural Overview

BlatUI Admin implements a multi-tenant-safe, decoupled administrative authentication and Role-Based Access Control (RBAC) foundation tailored for Laravel applications. The architecture isolates backend administrative identities from application-level user tables, provides hierarchical role and permission resolution, tracks system actions via automated audit logs, and supports dynamic tree-based navigation menus.

```
+-------------------------------------------------------------+
|                     Administrator Model                     |
|            (Authenticatable + HasPermissions)               |
+-------------------------------------------------------------+
                               |
                +--------------+--------------+
                |                             |
      (roles BelongsToMany)          (allPermissions)
                |                             |
                v                             v
        +---------------+             +---------------+
        |  Role Model   |------------>|  Permission   |
        +---------------+             +---------------+
                |
     (role_menu BelongsToMany)
                v
        +---------------+
        |  Menu Model   | (Tree Contract & ModelTree Trait)
        +---------------+
```

---

## Administrator Authentication & Guard Configuration

The administrative identity is represented by `BlatUI\Admin\Models\Administrator`.

### Key Characteristics:
- **Authentication Base**: Extends `Illuminate\Foundation\Auth\User`, conforming to Laravel's authenticatable contract.
- **Dedicated Guard**: Integrates with the dedicated `admin` session guard configured in `config/blatui-admin.php`.
- **Dynamic Table Resolution**: Overrides `getTable()` to read from `config('blatui-admin.database.users_table', 'admin_users')`, enabling custom table names without schema modifications.
- **Avatar Handling**: Provides an accessor for user avatars with fallback SVG generation or storage path resolution.

```php
namespace BlatUI\Admin\Models;

use BlatUI\Admin\Traits\HasPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Administrator extends Authenticatable
{
    use HasPermissions;
    use Notifiable;

    protected $fillable = ['username', 'password', 'name', 'avatar'];
    protected $hidden = ['password', 'remember_token'];

    public function getTable(): string
    {
        return config('blatui-admin.database.users_table', parent::getTable());
    }
}
```

---

## Role-Based Access Control (RBAC) Architecture

The authorization subsystem is decoupled into three primary models interconnected through pivot tables with timestamp tracking:

1. **`Administrator` (`admin_users`)**: The administrative actor.
2. **`Role` (`admin_roles`)**: A functional role grouping multiple permissions (e.g., `administrator`, `editor`).
3. **`Permission` (`admin_permissions`)**: Granular authorization primitives containing HTTP methods, path patterns, and hierarchical ordering.

### The `HasPermissions` Trait

Administrators gain authorization logic through `BlatUI\Admin\Traits\HasPermissions`:

- **Role Relationships**: `roles(): BelongsToMany` links administrators to `Role` instances via the `admin_role_users` pivot table.
- **Superuser Detection**: `isAdministrator()` inspects if the administrator possesses the `administrator` role slug.
- **Role Evaluation**: `isRole(string $role): bool` and `inRoles(array $roles): bool` test membership across assigned roles.
- **Aggregated Permission Resolution**: `allPermissions(): Collection` eagerly flattens permissions across all assigned roles and deduplicates them by primary identifier (`id`), preventing duplicate database overhead during permission evaluation.

```php
public function allPermissions(): Collection
{
    return $this->roles->loadMissing('permissions')->pluck('permissions')->flatten()->unique('id');
}
```

---

## Hierarchical Navigation Menus (`ModelTree`)

Dynamic backend navigation is managed via `BlatUI\Admin\Models\Menu`, which implements the `BlatUI\Admin\Contracts\Tree` interface through `BlatUI\Admin\Traits\ModelTree`.

### Tree Capabilities:
- **Self-Referential Hierarchy**: Configurable `parentColumn` (`parent_id`), `orderColumn` (`order`), and `titleColumn` (`title`).
- **Relational Accessors**: `parent(): BelongsTo` and `children(): HasMany` establish recursive navigation node relationships.
- **In-Memory Recursive Structuring**: `toTree(Collection $nodes, int $parentId = 0): array` converts flat query collections into nested tree hierarchies without recurring SQL round-trips.
- **Role Authorization on Menu Nodes**: Many-to-many relationship with `Role` via `admin_role_menu`, allowing navigation filtering based on administrator permissions.

---

## Audit Logging Subsystem (`OperationLog`)

User interactions within the administrative portal are captured by `BlatUI\Admin\Models\OperationLog`:

- **Attributes**: Records `user_id`, request `method` (GET, POST, PUT, DELETE), request `path`, client `ip`, and sanitized `input` payload serialized as JSON.
- **Dynamic Table Binding**: Resolves table name via `config('blatui-admin.database.operation_log_table', 'admin_operation_log')`.
- **User Relationship**: `user(): BelongsTo` binds the log entry to the configured administrator model class.

---

## Database Migrations & Initial Seeding

### Migration Schema (`database/migrations/2026_09_27_000000_create_admin_tables.php`)

The database architecture is provisioned via a single unified migration containing 8 relational tables:
1. `admin_users`: Primary administrative credentials and profile fields.
2. `admin_roles`: Role identifiers (`name`, unique `slug`).
3. `admin_permissions`: Granular endpoint access definitions (`http_method`, `http_path`, `parent_id`, `order`).
4. `admin_menu`: Tree structure for dynamic UI navigation (`parent_id`, `order`, `title`, `icon`, `uri`, `extension`, `show`).
5. `admin_role_users`: Administrator-to-Role pivot table.
6. `admin_role_permissions`: Role-to-Permission pivot table.
7. `admin_role_menu`: Role-to-Menu authorization pivot table.
8. `admin_operation_log`: Administrative audit trails.

### Seeder Pipeline (`database/seeders/AdminTablesSeeder.php`)

`AdminTablesSeeder` establishes idempotent bootstrap data:
- **Super Administrator**: Creates default user (`username: admin`, `password: admin`) hashed via `Illuminate\Support\Facades\Hash`.
- **Default Role**: Inserts `Administrator` (`slug: administrator`) and attaches it to the default user.
- **Foundational Permissions**: Generates `Auth management`, `Users`, `Roles`, `Permissions`, `Menu`, and `Operation log` permission boundaries.
- **System Navigation Hierarchy**: Builds the default navigation tree containing `Dashboard` and the `Admin` management sub-tree.
