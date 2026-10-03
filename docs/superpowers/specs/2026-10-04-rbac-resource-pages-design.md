> **Status: APPROVED (2026-10-04)**
> **Authority:** This document defines the implementation specification for RBAC Resource Pages in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md`.

# RBAC 资源管理页面 — Implementation Specification

## 1. Objective

Wire the Grid and Form engines to live, reachable resource pages so the four RBAC entities — Administrators, Roles, Permissions, Menus — have working list/create/edit/delete/batch-delete screens.

Today `AuthController` and `DashboardController` are the only concrete controllers; `AdminController` has no subclasses, and the RBAC routes the Seeder advertises in the sidebar (`auth/users`, `auth/roles`, `auth/permissions`, `auth/menu`) do not exist. The Grid row actions already emit `{resource}/{id}/edit`, `{resource}/{id}` and `{resource}/batch-delete` URLs that no route matches. This specification closes that gap and adds the two Form engine capabilities the RBAC forms require.

## 2. Current State (Evidenced)

1. **Controllers**: `src/Http/Controllers/` contains `AdminController` (abstract, `title`/`description`/`content()`), `AuthController`, `DashboardController`. No resource controllers exist.
2. **Routes**: `routes/blatui-admin.php` registers only `auth/login`, `auth/logout`, and `admin.home`.
3. **Engine URL conventions are fixed and already emitted**:
   - `src/Grid/Actions/Edit.php` `getUrl()` → `{resource}/{key}/edit`
   - `src/Grid/Actions/Delete.php` and `RowAction::getUrl()` → `{resource}/{key}`
   - `src/Grid/Tools/BatchDelete.php` `getUrl()` → `{resource}/batch-delete`
   - `src/Grid/Model.php` default resource derives from the model table (e.g. `/admin/admin_users`)
   - Seeder menu URIs are prefix-relative: `auth/users`, `auth/roles`, `auth/permissions`, `auth/menu` — matching `Menu::resolveUrl()`.
4. **Form engine gaps** (`src/Form.php`, `src/Form/Field.php`):
   - `Field\Select` is single-select only; no `multiple` support exists.
   - `Field::defaultVariables()` (`src/Form/Field.php:367-385`) coerces the value to a string scalar, so an array value cannot survive.
   - `Form::prepareDataForSave()` (`src/Form.php:566-596`) writes every input straight into the model's `create()`/`update()` payload. There is no concept of a field that targets a pivot table.
5. **Pivot tables already exist** in `database/migrations/2026_09_27_000000_create_admin_tables.php`: `admin_role_users`, `admin_role_permissions`, `admin_role_menu`.
6. **Authorization is inert**: `Permission::shouldPassThrough()` exists but no middleware calls it. Out of scope here.

## 3. Target Architecture

### 3.1 `ResourceController` base class

New file `src/Http/Controllers/ResourceController.php`, extending `AdminController`. It supplies the seven-action CRUD skeleton and delegates all Grid/Form configuration to the subclass through overridable hooks.

Actions:

| Method | Returns | Notes |
|---|---|---|
| `index()` | `Content` | Wraps `$this->grid()` |
| `create()` | `Content` | Wraps `$this->form(false)` |
| `store(Request)` | `RedirectResponse\|JsonResponse` | `$this->form(false)->store($request)` |
| `edit(int $id)` | `Content` | Wraps `$this->form(true)->edit($id)` |
| `update(Request, int $id)` | `RedirectResponse\|JsonResponse` | `$this->form(true)->update($id, $request)` |
| `destroy(int $id)` | `JsonResponse` | Repository delete + toast payload |
| `batchDestroy(Request)` | `JsonResponse` | Accepts `ids` array |

Overridable hooks, both with package defaults so a minimal subclass is two lines:

```php
protected function model(): string;      // Eloquent model class-string
protected function resource(): string;   // URL prefix, e.g. Admin::url('auth/users')
protected function grid(): Grid;
protected function form(bool $editing): Form;
```

`model()` defaults to a sensible per-resource value derived from the registered config key, so a minimal subclass declares only `grid()` and `form()`. `resource()` deliberately keeps the same name and semantics as `Grid::resource()`.

The base class contains no Grid column or Form field configuration. That configuration is the subclass's readable, ordinary-PHP responsibility; putting it in the base would turn the class into an implicit configuration framework.

### 3.2 Routes

`routes/blatui-admin.php` gains a resource registration inside the existing `Authenticate::class` group. URLs follow the engine's existing conventions exactly — no new convention is invented.

| Action | Verb + URL |
|---|---|
| index | `GET auth/users` |
| create | `GET auth/users/create` |
| store | `POST auth/users` |
| edit | `GET auth/users/{id}/edit` |
| update | `PUT auth/users/{id}` |
| destroy | `DELETE auth/users/{id}` |
| batchDestroy | `DELETE auth/users/batch-delete` |

**Route ordering constraint**: `batch-delete` is a static segment and must be registered *before* `{id}`; otherwise `{id}` swallows it. This ordering is asserted by a test.

Each resource gets a named route: `admin.users.index`, `admin.users.create`, `admin.users.store`, `admin.users.edit`, `admin.users.update`, `admin.users.destroy`, `admin.users.batch-destroy`, and the equivalents for `roles`, `permissions`, `menus`.

URL prefixes are built with `Admin::url('auth/users')`, honouring `config('blatui-admin.route.prefix')`.

### 3.3 Override mechanism

`config/blatui-admin.php` gains:

```php
'resources' => [
    'administrators' => AdministratorsController::class,
    'roles'         => RolesController::class,
    'permissions'   => PermissionsController::class,
    'menus'         => MenusController::class,
],
```

The route file reads this array and resolves each class name, falling back to the package-bundled class when the configured one does not exist. An application overrides a resource by editing config or publishing the file — no fork required.

### 3.4 `Form\Field\Relation`

New abstract class `src/Form/Field/Relation.php` extending `Field`. It marks a field whose value is written to a pivot table rather than the model's own table.

```php
abstract class Relation extends Field
{
    protected string $relation = '';
    public function relation(string $name): static;
    public function getRelation(): string;
}
```

### 3.5 `Form\Field\Multiselect`

New class `src/Form/Field/Multiselect.php` extending `Relation`. View: `blatui-admin::form.field.multiselect`.

- `$options` is a flat `key => label` map, matching the existing `Field\Select` contract exactly. No description sub-label (rejected: YAGNI — the four RBAC forms read correctly with title alone).
- `defaultVariables()` is **overridden**: the parent's string coercion at `src/Form/Field.php:367` must not apply. It returns `value` as an array of selected keys, plus `options`, `searchable`, and the standard label/help/required keys.

Blade template `resources/views/form/field/multiselect.blade.php` server-renders every option as a checkbox card inside an Alpine scope providing:

- a search input filtering visible options via `x-show`
- a select-all / clear toggle acting on currently visible options

Filtering uses `x-show`, not conditional rendering, so **with JS disabled every option remains visible and usable**. This matches the package's progressive-enhancement posture and the Alpine.js-via-CDN reality.

All markup and Tailwind classes live in Blade. All dynamic values pass through `{{ }}`.

### 3.6 Pivot persistence

`Form::prepareDataForSave()` collects `Relation` fields into a `$relations` array instead of writing them into `$data`. After `repository->store()` / `repository->update()` yields the record, `Form` syncs each relation:

```php
foreach ($relations as $field) {
    $record->{$field->getRelation()}()->sync($this->inputs[$field->getColumn()] ?? []);
}
```

`sync()` rather than `attach()`: editing a role must be able to *remove* a permission, and only `sync()` expresses both add and remove.

A `sync()` failure propagates as an exception, failing the whole request. Silent partial-success handling is rejected — a role saved with the wrong permission set is worse than a failed request.

### 3.7 The four resource controllers

New directory `src/Http/Controllers/Resources/`.

#### AdministratorsController

| | |
|---|---|
| Grid columns | `name`, `username`, `roles` (badge, comma-joined), `created_at` (datetime) |
| Grid filters | `username` like, `name` like, `created_at` between |
| Row actions | Edit, Delete |
| Form fields | `username` required, `name` required, `password` nullable, `roles` Multiselect → `role_users` pivot |

`username` validates `unique:admin_users,username,{id}` where `{id}` is the editing key or a placeholder on create. The `unique` rule must read the table name from `config('blatui-admin.database.users_table')`, not a hard-coded string.

Password handling requires one new `Form` method. `setInput()` calls `data_set()`, which *sets* a key rather than removing it, and `prepareDataForSave()` gates on `array_key_exists` — so a blank password on update would write an empty string over the stored hash. Add `public function forgetInput(string $key): static` beside `setInput()`.

The `saving` hook then hashes with `Hash::make`, and calls `forgetInput('password')` when the submitted value is empty so the stored hash is untouched.

#### RolesController

| | |
|---|---|
| Grid columns | `name`, `slug`, permissions count, `created_at` |
| Form fields | `name` required, `slug` required + `alpha_dash` + `unique:admin_roles,slug,{id}`, `permissions` Multiselect → `role_permissions`, `menus` Multiselect → `role_menu` |

Option labels for the permission and menu trees are built by flattening `parent_id, order`-ordered rows into indented strings (`'— Child'`). Parent/child structure is expressed through label indentation, not a nested field type; rejected: a nested tree field is a new field class with its own validation and value contract for no gain on a 50-column form.

#### PermissionsController

| | |
|---|---|
| Grid columns | `name`, `slug`, `http_method`, `http_path`, parent name, `order` |
| Form fields | `name` required, `slug` required + `alpha_dash` + unique, `parent_id` select, `order`, `http_method`, `http_path` textarea |

`parent_id` is a self-referencing select. When editing, the current record is excluded from its own options. The flat top-level (`0`) option is always present.

#### MenusController

| | |
|---|---|
| Grid columns | `title`, `icon`, `uri`, `order`, `show` (switch), `created_at` |
| Form fields | `title` required, `icon`, `uri`, `parent_id` tree select, `order`, `show` switch, `roles` Multiselect → `role_menu` |

Menus also write the `role_menu` pivot; `admin_role_menu` already exists in the migration.

**Rejected: a hidden `id` grid column.** `Grid\Column` has no `hidden()` method, and row keys already come from the model, so an `id` column would only render dead weight. Adding column visibility to the engine is out of scope for this work.

### 3.8 Guard rails on deletion
Deletion of the currently authenticated administrator, and of the built-in `administrator` role, is refused with a `403` and a toast message. Deleting either locks the installation out of its own management screens. Rejected: leaving this to application code — the package ships the seeder that creates both records, so it owns protecting them.

## 4. Data Flow

```
GET /admin/auth/users
  → Authenticate middleware
  → AdministratorsController@index
      → grid()  → Grid::make(Administrator::class)->resource(Admin::url('auth/users'))->column(...)->actions([Edit, Delete])
      → content($grid)  → Content renders layouts/app + grid/table
  → HTML: row actions point at /admin/auth/users/{id}/edit and DELETE /admin/auth/users/{id}

POST /admin/auth/users
  → AdministratorsController@store
      → form(false)  → Form::make(Administrator::class)->action(Admin::url('auth/users'))->redirect(Admin::url('auth/users'))
          fields: username, name, password (Field), roles (Relation\Multiselect)
      → Form::store($request)
          validate → saving hooks (hash password, unset empty) → repository->store()
          → sync('roles', input['roles'])
      → redirect back to /admin/auth/users + success toast
```

## 5. Error Handling

| Condition | Behaviour |
|---|---|
| Unauthenticated access to any resource route | `Authenticate` redirects to `admin.login` (existing behaviour) |
| Validation failure | `Form::store/update` returns `redirect()->back()->withErrors()->withInput()` (existing behaviour) |
| AJAX/JSON request failure | `422` with `errors` (existing behaviour) |
| `destroy` on missing key | `404` JSON |
| `destroy` on self or `administrator` role | `403` JSON with an explanatory `message` |
| `sync()` failure | Uncaught exception; request fails |
| Malformed `ids` in `batchDestroy` | `422` |

## 6. Testing

The gate is `composer test` (phpstan 0 errors, pint clean, pest green, 100% type coverage) plus `grep -rn '{!!' resources/views` returning no output.

New test files:

| File | Covers |
|---|---|
| `tests/Feature/ResourceRoutesTest.php` | All 28 resource routes registered under the configured prefix; `batch-delete` resolves to the batch endpoint and not to `{id}`; guest access redirects to login |
| `tests/Feature/Resource/AdministratorsTest.php` | index/create/store/edit/update/destroy/batch-destroy; role pivot asserted |
| `tests/Feature/Resource/RolesTest.php` | Full CRUD; permission and menu pivots asserted, including removal on update |
| `tests/Feature/Resource/PermissionsTest.php` | Full CRUD; `parent_id` self-exclusion when editing |
| `tests/Feature/Resource/MenusTest.php` | Full CRUD; role pivot asserted |
| `tests/Feature/Form/MultiselectFieldTest.php` | Rendering; array value survives `defaultVariables()`; options render server-side |
| `tests/Feature/Form/RelationPivotTest.php` | `sync()` adds and removes; `Relation` fields excluded from the model payload |

**Characterisation to preserve**: `tests/Feature/GridFeatureTest.php:176` asserts the default Grid resource is `/admin/admin_users`. Resource controllers set `->resource()` explicitly, so this assertion must continue to pass unmodified. No test inverting existing behaviour is required for this work.

## 7. Documentation Obligations

Per `AGENTS.md`, this specification and the shipped code must agree. Implementation therefore updates, in the same change:

- `AGENTS.md` — no new layer is introduced under `src/` beyond `ResourceController`, `Form/Field/Relation`, and `Form/Field/Multiselect`; the rendering contract is unchanged and the document already covers it. Add a line noting `Relation` fields are written to pivot tables, since that is a genuine extension of the ViewModel boundary.
- `README.md` — the resource pages become install-and-use functionality; the quick-start must show the routes.
- `resources/boost/skills/blatui-admin-development/SKILL.md` — regenerate if it documents the Form field list.
- `lang/en/admin.php`, `lang/zh_CN/admin.php` — add the resource page labels used by Grid titles and Form labels.

## 8. Explicitly Out of Scope

- **Authorization middleware.** `Permission::shouldPassThrough()` remains uncalled. The Seeder already writes `http_path` values; wiring them is a separate change with its own security review.
- **Operation Log page.** The Seeder seeds a `auth/logs` menu item and the `OperationLog` model exists, but no page is built here. Noted so the dangling menu entry is not mistaken for an oversight.
- **Show/detail pages.** `Show` row action exists in the engine but no resource registers it and no route backs it.
- **Avatar upload.** `Administrator::$fillable` accepts `avatar` and `getAvatarUrl()` falls back to ui-avatars.com; no upload field is added.
- **Visual redesign of Login or Dashboard.** Both already render and are not touched.

## 9. Known Inconsistency Not Fixed Here

`AuthController::redirectPath()` (`src/Http/Controllers/AuthController.php:74`) and `Authenticate::handle()` (`src/Http/Middleware/Authenticate.php:26`) each hand-build the login URL as `'/' . trim($prefix, '/') . '/auth/login'`, while `Admin::url()` (`src/Admin.php:66`) provides the same prefix logic. The duplication is pre-existing and the three implementations currently agree, so no bug ships. Consolidating them onto `Admin::url()` belongs to a separate cleanup; this specification uses `Admin::url()` for all new code so it does not extend the duplication.

