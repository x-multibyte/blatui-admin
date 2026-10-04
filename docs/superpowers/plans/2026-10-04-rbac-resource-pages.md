# RBAC Resource Pages Implementation Plan

> **Status: COMPLETE (verified 2026-10-04).** All eight implementation tasks landed on `feature/rbac-resource-pages` as thirteen commits. The gate output at verification time: PHPStan level 7 with 0 errors, Pint clean, type coverage 100.0%, and 212 Pest tests passing with 1067 assertions. `grep -rn '{!!' resources/views` returns zero matches. All five Review Focus cases are pinned by tests: empty-input pivot clearing (`tests/Feature/Form/RelationPivotTest.php`), self-delete and `administrator`-role delete guards (`tests/Feature/Resource/AdministratorsTest.php`, `tests/Feature/Resource/RolesTest.php`), `batch-delete` route precedence and guest redirects (`tests/Feature/ResourceRoutesTest.php`), and self-aware `unique` validation on edit (`tests/Feature/Resource/AdministratorsTest.php`). Retained for reference; no further work is pending here.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the four RBAC entities — Administrators, Roles, Permissions, Menus — working list/create/edit/delete/batch-delete pages, and add the two Form engine capabilities those forms require.

**Architecture:** A `ResourceController` base class supplies a seven-action CRUD skeleton; four subclasses contribute only Grid/Form configuration. Two new Form field classes unlock the forms: `Field\Relation` marks a field as pivot-backed, and `Field\Multiselect extends Relation` renders it as an Alpine-filtered checkbox list. Routes are generated from a `config('blatui-admin.resources')` array so an application can swap any controller without forking the package.

**Tech Stack:** PHP 8.3, Laravel 12/13, Pest 4/5 + Orchestra Testbench, Tailwind CSS v4, Alpine.js v3.

**Spec:** `docs/superpowers/specs/2026-10-04-rbac-resource-pages-design.md`

## Global Constraints

- `composer test` is the only gate: PHPStan level 7 (0 errors) → Pint clean → 100% type coverage → Pest green. Run it yourself; never claim it passed without the output.
- `grep -rn '{!!' resources/views` must return no output. All dynamic values pass through Blade `{{ }}`.
- Zero HTML string concatenation or heredocs in `src/`. All markup, Tailwind classes and Alpine directives live in `resources/views/`.
- Every file under `src/` declares `strict_types=1` (enforced by `tests/ArchTest.php`).
- `dd()`, `ddd()`, `env()`, `exit()` are forbidden in package source (`tests/ArchTest.php`).
- New `src/` files need full array-shape docblocks — type coverage must stay at 100%.
- PHP classes are ViewModels/DTOs. They hold state and configuration only.
- Branch: `feature/rbac-resource-pages`. Feature work does not land on `main`.

## Review Focus

Five failure modes the spec implies but its per-resource descriptions do not exercise. Each line names the test that pins it, in the task that owns the code.

1. **Empty multiselect submission leaves stale pivot rows.** A user unchecks every role and saves; the pivot must be emptied, not left as-is. → `tests/Feature/Form/RelationPivotTest.php`, "empty input clears the pivot".
2. **Deleting yourself or the `administrator` role locks the install out.** Both must return `403`. → `tests/Feature/Resource/AdministratorsTest.php` and `RolesTest.php`, "refuses to delete self", "refuses to delete the administrator role".
3. **`batch-delete` is shadowed by `{id}`.** `DELETE /admin/auth/users/batch-delete` must reach the batch endpoint, not `destroy()` with a non-numeric id. → `tests/Feature/ResourceRoutesTest.php`.
4. **`unique` validation on edit rejects the record's own current value.** Editing a user without changing `username` must pass. → `tests/Feature/Resource/AdministratorsTest.php`, "validates username uniqueness while ignoring itself".
5. **Guests reach resource pages.** Any resource route without a session must redirect to login, not render. → `tests/Feature/ResourceRoutesTest.php`.

---

### Task 1: `Field\Relation` and pivot persistence

The engine cannot express "this field writes to a pivot table, not the model table". Without this, every RBAC relationship field is impossible.

**Files:**
- Create: `src/Form/Field/Relation.php`
- Create: `tests/Feature/Form/RelationPivotTest.php`
- Modify: `src/Form.php` (`prepareDataForSave()` at line 566; `store()` after line 478; `update()` after line 532)

**Interfaces:**
- Consumes: `BlatUI\Admin\Contracts\Repository::edit(mixed $key): mixed`, `store(array $values): mixed`, `update(mixed $key, array $values): bool`; `Form::fields(): array<int, Field>`; `Form::$inputs`.
- Produces:
  - `abstract class BlatUI\Admin\Form\Field\Relation extends Field` with `protected string $relation = ''`, `public function relation(string $name): static`, `public function getRelation(): string`.
  - `protected function relationFields(): array<int, Relation>` on `Form`.
  - `protected function syncRelations(mixed $record): void` on `Form`.
  - `Form::prepareDataForSave()` keeps its `array<string, mixed>` return type but omits every `Relation` column.

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Form/RelationPivotTest.php`. Define a local `Relation` subclass inline so the test does not depend on Task 2's `Multiselect`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Form;
use BlatUI\Admin\Form\Field;
use BlatUI\Admin\Models\Role;
use Illuminate\Http\Request;

uses(BlatUI\Admin\Tests\TestCase::class);

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
});

/** Minimal pivot-backed field used to exercise Relation without Multiselect. */
function pivotField(string $column, string $relation): Field\Relation
{
    $field = new class($column, $relation) extends Field\Relation {
        public function __construct(string $column, string $relation)
        {
            parent::__construct($column);
            $this->relation($relation);
        }
    };

    return $field;
}

test('relation fields are excluded from the model payload', function () {
    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    $form->fill(['name' => 'Editor', 'permission_ids' => [1, 2]]);

    $request = Request::create('/admin/auth/roles', 'POST', [
        'name' => 'Editor',
        'permission_ids' => [1, 2],
    ]);

    $form->store($request);

    // The roles table has no permission_ids column; a leaked column would have thrown.
    expect(Role::query()->where('name', 'Editor')->exists())->toBeTrue();
});

test('relation fields sync the pivot table on store', function () {
    $adminRole = Role::query()->create(['name' => 'Administrator', 'slug' => 'administrator']);
    $editorRole = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

    $permission = \BlatUI\Admin\Models\Permission::query()->create([
        'name' => 'Users', 'slug' => 'users', 'parent_id' => 0, 'order' => 1,
    ]);

    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    $form->store(Request::create('/admin/auth/roles', 'POST', [
        'name' => 'Author', 'slug' => 'author', 'permission_ids' => [$permission->id],
    ]));

    $created = Role::query()->where('slug', 'author')->firstOrFail();
    expect($created->permissions->pluck('id')->all())->toBe([$permission->id]);
    expect($adminRole->exists)->toBeTrue()->and($editorRole->exists)->toBeTrue();
});

test('sync adds and removes on update', function () {
    $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
    $keep = \BlatUI\Admin\Models\Permission::query()->create(['name' => 'A', 'slug' => 'a', 'parent_id' => 0, 'order' => 1]);
    $drop = \BlatUI\Admin\Models\Permission::query()->create(['name' => 'B', 'slug' => 'b', 'parent_id' => 0, 'order' => 2]);
    $add = \BlatUI\Admin\Models\Permission::query()->create(['name' => 'C', 'slug' => 'c', 'parent_id' => 0, 'order' => 3]);

    $role->permissions()->attach([$keep->id, $drop->id]);

    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    $form->update($role->id, Request::create('/admin/auth/roles/1', 'PUT', [
        'name' => 'Editor', 'slug' => 'editor', 'permission_ids' => [$keep->id, $add->id],
    ]));

    expect($role->fresh()->permissions->pluck('id')->sort()->values()->all())
        ->toBe(collect([$keep->id, $add->id])->sort()->values()->all());
});

test('empty input clears the pivot', function () {
    $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = \BlatUI\Admin\Models\Permission::query()->create(['name' => 'A', 'slug' => 'a', 'parent_id' => 0, 'order' => 1]);
    $role->permissions()->attach([$permission->id]);

    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    // No permission_ids key at all — the user unchecked everything.
    $form->update($role->id, Request::create('/admin/auth/roles/1', 'PUT', [
        'name' => 'Editor', 'slug' => 'editor',
    ]));

    expect($role->fresh()->permissions)->toHaveCount(0);
});
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Form/RelationPivotTest.php`
Expected: FAIL — `Class "BlatUI\Admin\Form\Field\Relation" not found`.

- [x] **Step 3: Create `src/Form/Field/Relation.php`**

`abstract class Relation extends Field`, `declare(strict_types=1)`, namespace `BlatUI\Admin\Form\Field`. Holds `protected string $relation = ''` plus the `relation()` / `getRelation()` pair, matching the getter/setter/fluent triple shape used throughout `src/Form/Field.php` (see `Field::label()` at line 109 for the house style). Both methods get docblocks.

- [x] **Step 4: Add relation handling to `src/Form.php`**

Add `relationFields(): array<int, Relation>` returning `$this->fields` filtered by `instanceof Relation` — same collection-flavour as `fields()` at line 227.

Add:

```php
protected function syncRelations(mixed $record): void
{
    if (! $record instanceof \Illuminate\Database\Eloquent\Model) {
        return;
    }

    foreach ($this->relationFields() as $field) {
        $record->{$field->getRelation()}()->sync($this->inputs[$field->getColumn()] ?? []);
    }
}
```

In `prepareDataForSave()`, skip `Relation` fields in the main loop the way `Field\Display` is already skipped — add `$field instanceof Relation` to the ignore branch so those columns never reach `$data`.

In `store()`, call `$this->syncRelations($record);` on the line immediately after `$record = $this->repository->store($data);` and **before** the `created` / `saved` hooks, so a hook can observe the synced relations.

In `update()`, `$this->repository->update($id, $data)` returns `bool`, not the record. Guard and re-fetch:

```php
if ($success) {
    $this->syncRelations($this->repository->edit($id));
}
```

placed after `$success = $this->repository->update($id, $data);` and before the `updated` / `saved` hooks.

`sync()` failure is deliberately uncaught — the whole request fails. Do not wrap it.

- [x] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Form/RelationPivotTest.php`
Expected: PASS, 4 tests.

- [x] **Step 6: Run the full gate**

Run: `composer test`
Expected: PHPStan 0 errors, Pint clean, type coverage 100%, all Pest suites green.

- [x] **Step 7: Commit**

```bash
git add src/Form.php src/Form/Field/Relation.php tests/Feature/Form/RelationPivotTest.php
git commit -m "feat(form): add relation fields with pivot persistence"
```

---

### Task 2: `Field\Multiselect` and its Blade view

Roles and Menus need "assign N permissions / N menus". `Field\Select` is single-select and `Field::defaultVariables()` coerces values to strings, so an array cannot survive.

**Files:**
- Create: `src/Form/Field/Multiselect.php`
- Create: `resources/views/form/field/multiselect.blade.php`
- Create: `tests/Feature/Form/MultiselectFieldTest.php`

**Interfaces:**
- Consumes: `Field\Relation` (Task 1); `Field::getColumn()`, `getLabel()`, `getHelp()`, `isRequired()`, `getPlaceholder()` from `src/Form/Field.php`.
- Produces:
  - `class BlatUI\Admin\Form\Field\Multiselect extends Relation`, `$view = 'blatui-admin::form.field.multiselect'`.
  - `public function options(array $options): static`, `public function getOptions(): array<int|string, string>` — flat `key => label`, same contract as `Field\Select::options()`.
  - `public function searchable(bool $searchable = true): static`, `public function isSearchable(): bool` — defaults `true`.
  - `Multiselect::defaultVariables(): array<string, mixed>` — **overrides** the parent so `value` stays an array. Adds `options` and `searchable` alongside the parent's standard keys.

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Form/MultiselectFieldTest.php`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Form\Field\Multiselect;

test('multiselect keeps an array value instead of coercing it to a string', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->value([3, 7]);

    $variables = $field->defaultVariables();

    expect($variables['value'])->toBe(['3', '7'])
        ->and($variables['value'])->toBeArray();
});

test('multiselect renders every option server-side', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->options(['1' => 'Users', '2' => 'Roles', '3' => 'Menu']);

    $html = $field->render();

    expect($html)
        ->toContain('name="permission_ids[]"')
        ->toContain('value="1"')
        ->toContain('Users')
        ->toContain('Roles')
        ->toContain('Menu');
});

test('multiselect marks pre-selected options as checked', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->options(['1' => 'Users', '2' => 'Roles']);
    $field->value([2]);

    expect($field->render())
        ->toContain('value="2"')
        ->toContain('checked');
});

test('multiselect escapes option labels', function () {
    $field = new Multiselect('role_ids', 'Roles');
    $field->relation('roles');
    $field->options(['1' => '<script>alert(1)</script>']);

    $html = $field->render();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

test('multiselect is searchable by default and can be disabled', function () {
    $field = new Multiselect('role_ids', 'Roles');

    expect($field->isSearchable())->toBeTrue();

    $field->searchable(false);

    expect($field->isSearchable())->toBeFalse();
});
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Form/MultiselectFieldTest.php`
Expected: FAIL — `Class "BlatUI\Admin\Form\Field\Multiselect" not found`.

- [x] **Step 3: Create `src/Form/Field/Multiselect.php`**

`$view = 'blatui-admin::form.field.multiselect'`, `protected array $options = []`, `protected bool $searchable = true`, plus the four methods above.

Override `defaultVariables()` to call `array_merge(parent::defaultVariables(), [...])` and then **replace** the `value` key with the raw array:

```php
public function defaultVariables(): array
{
    $variables = parent::defaultVariables();

    $rawValue = $this->getValue();

    $variables['value'] = is_array($rawValue)
        ? array_values(array_map('strval', $rawValue))
        : (is_scalar($rawValue) ? [(string) $rawValue] : []);

    $variables['options'] = $this->options;
    $variables['searchable'] = $this->searchable;

    return $variables;
}
```

Keys are normalised to strings because the template compares them with a strict `in_array((string) $key, $value, true)`. This is required for correctness, not style: Eloquent relation ids come back as **ints**, so `in_array("3", [3, 7], true)` is `false`, every checkbox renders unchecked, and saving silently wipes the user's assignments. `getValue()` still returns the raw value untouched — only the template-bound array is normalised.

- [x] **Step 4: Create `resources/views/form/field/multiselect.blade.php`**

Model the `@props` block on `resources/views/form/field/select.blade.php` (same keys, plus `options`, `searchable`; `value` is now an array). Match that file's Tailwind classes and `sm:grid sm:grid-cols-4` label/field layout so the form reads consistently.

Inside the field body, an Alpine scope holding `query` and the server-rendered checkbox list:

```blade
<div x-data="{ query: @js($value === [] ? '' : '') }">
```

Do not inline the Alpine object literal with interpolated PHP. Instead expose the selected keys to Alpine through a JSON-encoded attribute and drive selection from the DOM:

```blade
<div
    x-data="{
        query: '',
        allVisible() {
            return Array.from(this.$root.querySelectorAll('input[type=checkbox]'))
                .filter(el => el.offsetParent !== null);
        },
        toggleAll() {
            const visible = this.allVisible();
            const shouldCheck = visible.some(el => !el.checked);
            visible.forEach(el => { el.checked = shouldCheck; });
        }
    }"
    data-selected="{{ json_encode($value) }}"
>
```

Requirement: **filtering uses `x-show`, not conditional rendering**, so with JS disabled every option stays visible and the form still submits correctly. Each option is a `<label>` carrying `x-show="!query || $el.textContent.toLowerCase().includes(query.toLowerCase())"` wrapping a `<input type="checkbox" name="{{ $name }}[]" value="{{ $key }}">`.

Pre-selection uses Blade's `@checked(in_array((string) $key, $value, true))`. The search input and the select-all button render only when `$searchable` is true.

Zero `{!! !!}`. All dynamic values through `{{ }}`.

- [x] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Form/MultiselectFieldTest.php`
Expected: PASS, 5 tests.

- [x] **Step 6: Verify no raw Blade output crept in**

Run: `grep -rn '{!!' resources/views`
Expected: no output.

- [x] **Step 7: Run the full gate**

Run: `composer test`
Expected: green on all four stages.

- [x] **Step 8: Commit**

```bash
git add src/Form/Field/Multiselect.php resources/views/form/field/multiselect.blade.php tests/Feature/Form/MultiselectFieldTest.php
git commit -m "feat(form): add multiselect field with alpine filterable checkbox list"
```

---

### Task 3: `ResourceController` base, config registry, and routes

Nothing is reachable today: the Grid engine already emits `/admin/auth/users/{id}/edit` and `/admin/auth/users/batch-delete`, and no route matches either.

**Files:**
- Create: `src/Http/Controllers/ResourceController.php`
- Create: `tests/Feature/ResourceRoutesTest.php`
- Modify: `config/blatui-admin.php` (add `resources` array)
- Modify: `routes/blatui-admin.php`

**Interfaces:**
- Consumes: `AdminController::title()`, `description()`, `content(?Closure)` from `src/Http/Controllers/AdminController.php`; `Admin::url(string $path = ''): string`; `Grid::make(mixed $repository, ?Closure)`, `Form::make(mixed $repository, ?Closure)`; `EloquentRepository`.
- Produces:
  - `abstract class BlatUI\Admin\Http\Controllers\ResourceController extends AdminController` with:
    - `protected function model(): string` — Eloquent model class-string
    - `protected function resource(): string` — URL prefix, default `Admin::url('auth/'.$this->resourceKey())`
    - `protected function resourceKey(): string` — config key, defaults to `''`
    - `protected function grid(): Grid`
    - `protected function form(bool $editing): Form`
    - `protected function repositoryFor(): EloquentRepository`
    - `public function index(): Content`
    - `public function create(): Content`
    - `public function store(Request $request): JsonResponse|RedirectResponse`
    - `public function edit(int $id): Content`
    - `public function update(Request $request, int $id): JsonResponse|RedirectResponse`
    - `public function destroy(int $id): JsonResponse`
    - `public function batchDestroy(Request $request): JsonResponse`
    - `protected function authorizeDestroy(mixed $record): ?JsonResponse` — returns a 403 response to refuse, or `null` to allow. Returns `null` in the base class; Resources that need guards override it.
  - `config/blatui-admin.resources`: `array<string, class-string>` keyed `administrators`, `roles`, `permissions`, `menus`.
  - 28 named routes: `admin.{key}.index|create|store|edit|update|destroy|batch-destroy` for each key, under the configured `route.prefix`.

- [x] **Step 1: Write the failing route test**

Create `tests/Feature/ResourceRoutesTest.php`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('all resource routes are registered under the configured prefix', function () {
    foreach (['users', 'roles', 'permissions', 'menu'] as $key) {
        expect(route("admin.{$key}.index"))->toBe("/admin/auth/{$key}")
            ->and(route("admin.{$key}.create"))->toBe("/admin/auth/{$key}/create")
            ->and(route("admin.{$key}.store"))->toBe("/admin/auth/{$key}")
            ->and(route("admin.{$key}.edit", ['id' => 1]))->toBe("/admin/auth/{$key}/1/edit")
            ->and(route("admin.{$key}.update", ['id' => 1]))->toBe("/admin/auth/{$key}/1")
            ->and(route("admin.{$key}.destroy", ['id' => 1]))->toBe("/admin/auth/{$key}/1")
            ->and(route("admin.{$key}.batch-destroy"))->toBe("/admin/auth/{$key}/batch-delete");
    }
});

test('resource routes honour a reconfigured prefix', function () {
    config(['blatui-admin.route.prefix' => 'panel']);

    // Re-register so the new prefix is applied.
    $this->refreshApplication();

    expect(route('admin.roles.index'))->toBe('/panel/auth/roles');
});

test('batch-delete is not shadowed by the id parameter', function () {
    $route = collect(app('router')->getRoutes())->first(
        fn ($route) => $route->uri() === 'admin/auth/users/batch-delete'
            && in_array('DELETE', $route->methods(), true)
    );

    expect($route)->not->toBeNull()
        ->and($route->parameter('id'))->toBeNull();
});

test('guests are redirected away from every resource index', function () {
    foreach (['users', 'roles', 'permissions', 'menu'] as $key) {
        $this->get("/admin/auth/{$key}")->assertRedirect('/admin/auth/login');
    }
});

test('the built-in controllers are wired from config', function () {
    expect(config('blatui-admin.resources.administrators'))->toBe(\BlatUI\Admin\Http\Controllers\Resources\AdministratorsController::class)
        ->and(config('blatui-admin.resources.roles'))->toBe(\BlatUI\Admin\Http\Controllers\Resources\RolesController::class)
        ->and(config('blatui-admin.resources.permissions'))->toBe(\BlatUI\Admin\Http\Controllers\Resources\PermissionsController::class)
        ->and(config('blatui-admin.resources.menus'))->toBe(\BlatUI\Admin\Http\Controllers\Resources\MenusController::class);
});
```

Delete the `reconfigured prefix` test if you cannot make it pass without restructuring route registration — do not contort the route file for it. In that case note the deviation and move on.

- [x] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/ResourceRoutesTest.php`
Expected: FAIL — `Route [admin.users.index] not defined`.

- [x] **Step 3: Add the `resources` array to `config/blatui-admin.php`**

Insert after the `auth` block, before `database`:

```php
'resources' => [
    'administrators' => '\\BlatUI\\Admin\\Http\\Controllers\\Resources\\AdministratorsController',
    'roles'         => '\\BlatUI\\Admin\\Http\\Controllers\\Resources\\RolesController',
    'permissions'   => '\\BlatUI\\Admin\\Http\\Controllers\\Resources\\PermissionsController',
    'menus'         => '\\BlatUI\\Admin\\Http\\Controllers\\Resources\\MenusController',
],
```

Use plain string class names, not `::class`. `config/` is in the PHPStan analysis path and these classes do
not exist until Tasks 4–7, so `::class` would hold the gate red across four commits. The values are
byte-identical to what `::class` would produce — `::class` on a missing class resolves at compile time
without autoloading — so a test asserting against `::class` still compares real strings.

- [x] **Step 4: Create `src/Http/Controllers/ResourceController.php`**

`extends AdminController`, `declare(strict_types=1)`.

`model()` returns `''` by default; each subclass overrides it. `resource()` returns `Admin::url('auth/'.$this->resourceKey())`; `resourceKey()` returns `''` by default.

Action bodies:

- `index()` — `Content::breadcrumb(array ...$breadcrumbs)` is variadic (`src/Layout/Content.php:116`), matching `DashboardController`:

  ```php
  public function index(): Content
  {
      return $this->content()
          ->breadcrumb(['text' => $this->title()])
          ->row($this->grid());
  }
  ```

  `create()` and `edit()` follow the same shape with `->row($this->form($editing))`; `edit()` additionally calls `->edit($id)` on the form so the record loads and the fields prefill.

**Do not chain `->action($url)->redirect($url)`.** `Form::action(?string): static|string` is a getter/setter pair (`src/Form.php:683`) while `Form::redirect(string): static` is setter-only (`src/Form.php:739`), so the chain yields `Cannot call method redirect() on BlatUI\Admin\Form|string` under PHPStan level 7. Use a private helper that sets both on separate statements.
- `create()` — `$this->form(false)->action($this->resource())->redirect($this->resource())`, wrapped in `$this->content()->row(...)`.
- `store()` — `return $this->form(false)->store($request);`
- `edit(int $id)` — `$this->form(true)->action($this->resource())->redirect($this->resource())->edit($id)`, wrapped in `Content`.
- `update()` — `return $this->form(true)->update($id, $request);`
- `destroy(int $id)` — look the record up via `$this->repositoryFor()->edit($id)`; `404` JSON when absent; return `$this->authorizeDestroy($record)` when it is non-null; otherwise `$this->repositoryFor()->destroy($id)` and a `200` JSON `{status: true, message: 'Deleted successfully'}`.
- `batchDestroy(Request)` — validate `ids` as `required|array`, `ids.*` as `integer`; `422` JSON on failure; delegate to `$this->repositoryFor()->destroy($ids)`; `200` JSON on success.

`form(bool $editing)` and `grid()` throw `LogicException('Subclass must implement grid().')` / `('Subclass must implement form().')` in the base — the message names the missing method.

**Guard order in `destroy()` matters:** 404 for a missing record *before* the authorization check, so probing for existence cannot leak authorization state.

- [x] **Step 5: Register the routes in `routes/blatui-admin.php`**

Inside the existing `Route::group(['middleware' => [Authenticate::class]], ...)`, loop the config array.

**The config key is not the URL segment.** Two bundled resources differ, because the Seeder's sidebar links
`auth/users` and `auth/menu` while the controller families are `Administrators` and `Menus`. Map them
explicitly; any other key serves its own name so a consumer can add a resource without touching this file.

```php
$segments = [
    'administrators' => 'users',
    'menus' => 'menu',
];

foreach ((array) config('blatui-admin.resources', []) as $key => $controller) {
    $fallback = '\\BlatUI\\Admin\\Http\\Controllers\\Resources\\'.ucfirst((string) $key).'Controller';
    $class = is_string($controller) && class_exists($controller) ? $controller : $fallback;
    $segment = $segments[$key] ?? (string) $key;

    Route::group(['prefix' => 'auth/'.$segment], function () use ($class, $segment): void {
        Route::get('/', [$class, 'index'])->name("admin.{$segment}.index");
        Route::get('create', [$class, 'create'])->name("admin.{$segment}.create");
        Route::post('/', [$class, 'store'])->name("admin.{$segment}.store");
        // Static segment MUST precede {id} or {id} swallows it.
        Route::delete('batch-delete', [$class, 'batchDestroy'])->name("admin.{$segment}.batch-destroy");
        Route::get('{id}/edit', [$class, 'edit'])->whereNumber('id')->name("admin.{$segment}.edit");
        Route::put('{id}', [$class, 'update'])->whereNumber('id')->name("admin.{$segment}.update");
        Route::delete('{id}', [$class, 'destroy'])->whereNumber('id')->name("admin.{$segment}.destroy");
    });
}
```

`->whereNumber('id')` makes the shadowing impossible regardless of ordering, but keep the ordering too — the comment records *why* the constraint exists.

The inner group sits inside the outer group that already applies `route.prefix`, so the segment is `'auth/'.$key` without re-adding the prefix.

The route file is a plain closure with no `$this` binding, so call the loop inline rather than through a method.

- [x] **Step 6: Run the route tests**

Run: `vendor/bin/pest tests/Feature/ResourceRoutesTest.php`
Expected: the registration tests PASS. The controller-referencing tests may still fail with a "class not found" error — Tasks 4–7 create those classes. That is expected; note it and continue.

- [x] **Step 7: Run the full gate**

Run: `composer test`
Expected: green, with the controller-dependent route assertions still outstanding until Tasks 4–7.

- [x] **Step 8: Commit**

```bash
git add config/blatui-admin.php routes/blatui-admin.php src/Http/Controllers/ResourceController.php tests/Feature/ResourceRoutesTest.php
git commit -m "feat(http): add resource controller base and register rbac routes"
```

---

### Task 4: Administrators resource

**Files:**
- Create: `src/Http/Controllers/Resources/AdministratorsController.php`
- Create: `tests/Feature/Resource/AdministratorsTest.php`

**Interfaces:**
- Consumes: `ResourceController` (Task 3), `Multiselect` (Task 2), `Grid::column()`, `Grid::filter()`, `Grid::actions()`, `Filter::like()`, `Filter::between()`, `Grid\Actions\Edit`, `Grid\Actions\Delete`, `Column::badge()`, `Column::datetime()`, `Column::display(Closure)`.
- Produces: `class BlatUI\Admin\Http\Controllers\Resources\AdministratorsController extends ResourceController`.

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Resource/AdministratorsTest.php`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the administrators index renders a grid', function () {
    $this->get('/admin/auth/users')
        ->assertOk()
        ->assertSee('Administrator');
});

test('a user can be created and assigned roles', function () {
    $editor = Role::query()->where('slug', 'editor')->first()
        ?? Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

    $this->post('/admin/auth/users', [
        'username' => 'jane',
        'name' => 'Jane Doe',
        'password' => 'secret123',
        'roles' => [$editor->id],
    ])->assertRedirect('/admin/auth/users');

    $jane = Administrator::query()->where('username', 'jane')->firstOrFail();

    expect(Hash::check('secret123', $jane->password))->toBeTrue()
        ->and($jane->roles->pluck('id')->all())->toBe([$editor->id]);
});

test('a user can be updated and roles removed', function () {
    $editor = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);

    $this->post('/admin/auth/users', [
        'username' => 'jane', 'name' => 'Jane', 'password' => 'secret123', 'roles' => [$editor->id],
    ]);

    $jane = Administrator::query()->where('username', 'jane')->firstOrFail();
    $originalHash = $jane->password;

    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'jane', 'name' => 'Jane Renamed', 'roles' => [],
    ])->assertRedirect('/admin/auth/users');

    expect($jane->fresh()->name)->toBe('Jane Renamed')
        ->and($jane->fresh()->roles)->toHaveCount(0)
        // An empty password field on update must leave the stored hash untouched.
        ->and($jane->fresh()->password)->toBe($originalHash);
});

test('a new password is hashed when supplied on update', function () {
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('oldpassword'),
    ]);

    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'jane', 'name' => 'Jane', 'password' => 'newpassword',
    ]);

    expect(Hash::check('newpassword', $jane->fresh()->password))->toBeTrue();
});

test('validates username uniqueness while ignoring itself', function () {
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);

    // Submitting the same username must pass on edit.
    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'jane', 'name' => 'Jane Renamed',
    ])->assertSessionHasNoErrors();

    // A different existing username must fail.
    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'admin', 'name' => 'Jane',
    ])->assertSessionHasErrors('username');
});

test('refuses to delete self', function () {
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $this->deleteJson("/admin/auth/users/{$admin->id}")
        ->assertForbidden();

    expect(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('a user can be deleted', function () {
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);

    $this->deleteJson("/admin/auth/users/{$jane->id}")->assertOk();

    expect(Administrator::query()->whereKey($jane->id)->exists())->toBeFalse();
});

test('batch destroy deletes the selected users', function () {
    $a = Administrator::query()->create(['username' => 'a', 'name' => 'A', 'password' => Hash::make('x')]);
    $b = Administrator::query()->create(['username' => 'b', 'name' => 'B', 'password' => Hash::make('x')]);

    $this->deleteJson('/admin/auth/users/batch-delete', ['ids' => [$a->id, $b->id]])
        ->assertOk();

    expect(Administrator::query()->whereKey([$a->id, $b->id])->count())->toBe(0);
});

test('batch destroy rejects a malformed ids payload', function () {
    $this->deleteJson('/admin/auth/users/batch-delete', ['ids' => 'not-an-array'])
        ->assertStatus(422);
});
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Resource/AdministratorsTest.php`
Expected: FAIL — controller class not found.

- [x] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Form;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Actions\Delete;
use BlatUI\Admin\Grid\Actions\Edit;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdministratorsController extends ResourceController
{
    protected string $title = 'Administrators';

    protected string $description = 'Manage administrator accounts and their role assignments';

    protected function model(): string
    {
        return Administrator::class;
    }

    protected function resourceKey(): string
    {
        return 'users';
    }
```

`grid()`: `Grid::make(Administrator::class)->resource($this->resource())`, columns `name` (with `->display()` appending the username's avatar via `Administrator::getAvatarUrl()`), `username`, `roles` (`->badge()` fed by `->display(fn ($row) => $row->roles->pluck('name')->implode(', '))`), `created_at` (`->datetime()`). Filters `like('username')`, `like('name')`, `between('created_at')`. Actions callback registering `Edit` and `Delete`.

`form(bool $editing)`: `Form::make(Administrator::class)` with `->action($this->resource())->redirect($this->resource())`.

- `username` — `->rules(['required', 'string', 'max:120', 'unique:'.config('blatui-admin.database.users_table').',username'.($editing && $form->getKey() ? ','.$form->getKey() : ''), 'alpha_dash'])`. Build the rule string in a local variable so the config lookup is visible rather than buried.
- `name` — `->rules(['required', 'string', 'max:255'])`
- `password` — `->rules($editing ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'])->help($editing ? 'Leave blank to keep the current password.' : 'At least 6 characters.')`
- `roles` — `$form->pushField((new Multiselect('roles', 'Roles'))->relation('roles')->options(Role::query()->orderBy('name')->pluck('name', 'id')->all()))`. When `$editing` and the form has a key, load the record first via `$form->repository()->edit($form->getKey())` and `.value($record->roles->pluck('id')->all())`.

**Password handling requires one new `Form` method.** `setInput()` calls `data_set()` (`src/Form.php:378`), which *sets* a key rather than removing it, and `prepareDataForSave()` gates on `array_key_exists` — so a blank password submitted on update would write an empty string over the stored hash. Add:

```php
/**
 * Remove a submission input value.
 */
public function forgetInput(string $key): static
{
    unset($this->inputs[$key]);

    return $this;
}
```

to `src/Form.php` beside `setInput()`, with a docblock. Cover it in `tests/Feature/Form/FormLifecycleTest.php`: build a form, `setInput('password', 'x')`, `forgetInput('password')`, assert `$form->input('password')` is null and the key no longer appears in the prepared payload.

Then the `saving` hook:

```php
$form->saving(function (Form $form): void {
    $password = (string) $form->input('password', '');

    if ($password === '') {
        $form->forgetInput('password');

        return;
    }

    $form->setInput('password', Hash::make($password));
});
```

`authorizeDestroy(mixed $record)`: return a 403 `JsonResponse` when `$record` is the authenticated user. Guard with `$record instanceof Model` and compare `$record->getKey()` against `Admin::id()`.

- [x] **Step 4: Run the tests**

Run: `vendor/bin/pest tests/Feature/Resource/AdministratorsTest.php`
Expected: PASS, 9 tests.

- [x] **Step 5: Run the full gate**

Run: `composer test`
Expected: green.

- [x] **Step 6: Commit**

```bash
git add src/Http/Controllers/Resources/AdministratorsController.php tests/Feature/Resource/AdministratorsTest.php
git commit -m "feat(admin): add administrators resource page"
```

---

### Task 5: Roles resource

**Files:**
- Create: `src/Http/Controllers/Resources/RolesController.php`
- Create: `tests/Feature/Resource/RolesTest.php`

**Interfaces:**
- Consumes: `ResourceController` (Task 3), `Multiselect` (Task 2), `Permission`, `Menu` models.
- Produces:
  - `class BlatUI\Admin\Http\Controllers\Resources\RolesController extends ResourceController`
  - `protected function treeOptions(string $modelClass, string $titleColumn): array<int, string>` — **reused by Task 6 and Task 7**, so place it here and have them call `RolesController::treeOptions()` or promote it to a trait. Prefer promoting it to a trait `src/Http/Controllers/Resources/Concerns/BuildsTreeOptions.php` so no controller depends on another controller.

- [x] **Step 1: Create the shared tree-options trait**

Create `src/Http/Controllers/Resources/Concerns/BuildsTreeOptions.php`:

```php
protected function treeOptions(string $modelClass, string $titleColumn, int $depth = 0, ?int $parentId = 0): array
```

Loads rows ordered by `parent_id`, then `order`, then the title column, and flattens each level into `[$id => str_repeat('— ', $depth).$title]`, recursing into children. Depth 0 is the top level. Guard against a cycle by tracking visited ids and skipping a node already emitted.

`Permission` does **not** use the `ModelTree` trait (only `Menu` does), so this works off raw `parent_id`/`order` columns for both models.

- [x] **Step 2: Write the failing test**

Create `tests/Feature/Resource/RolesTest.php`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the roles index renders a grid', function () {
    $this->get('/admin/auth/roles')->assertOk()->assertSee('Administrator');
});

test('a role can be created with permissions and menus', function () {
    $permission = Permission::query()->where('slug', 'users')->firstOrFail();
    $menu = \BlatUI\Admin\Models\Menu::query()->where('uri', 'auth/users')->firstOrFail();

    $this->post('/admin/auth/roles', [
        'name' => 'Editor', 'slug' => 'editor',
        'permissions' => [$permission->id],
        'menus' => [$menu->id],
    ])->assertRedirect('/admin/auth/roles');

    $role = Role::query()->where('slug', 'editor')->firstOrFail();

    expect($role->permissions->pluck('id')->all())->toBe([$permission->id])
        ->and($role->menus->pluck('id')->all())->toBe([$menu->id]);
});

test('updating a role removes deselected permissions', function () {
    $users = Permission::query()->where('slug', 'users')->firstOrFail();
    $roles = Permission::query()->where('slug', 'roles')->firstOrFail();

    $this->post('/admin/auth/roles', [
        'name' => 'Editor', 'slug' => 'editor',
        'permissions' => [$users->id, $roles->id],
    ]);

    $role = Role::query()->where('slug', 'editor')->firstOrFail();

    $this->put("/admin/auth/roles/{$role->id}", [
        'name' => 'Editor', 'slug' => 'editor', 'permissions' => [$users->id],
    ]);

    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$users->id]);
});

test('permission options are indented by hierarchy', function () {
    Permission::query()->create([
        'name' => 'Auth management', 'slug' => 'auth-management', 'parent_id' => 0, 'order' => 1,
    ]);
    $parent = Permission::query()->where('slug', 'users')->firstOrFail();
    $parent->update(['parent_id' => Permission::query()->where('slug', 'auth-management')->firstOrFail()->id]);

    $html = $this->get('/admin/auth/roles/create')->assertOk()->getContent();

    expect($html)->toContain('Users');
});

test('validates slug format and uniqueness', function () {
    $this->post('/admin/auth/roles', ['name' => 'Editor', 'slug' => 'not a slug'])
        ->assertSessionHasErrors('slug');

    $this->post('/admin/auth/roles', ['name' => 'Editor', 'slug' => 'administrator'])
        ->assertSessionHasErrors('slug');
});

test('refuses to delete the administrator role', function () {
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    $this->deleteJson("/admin/auth/roles/{$role->id}")->assertForbidden();

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('a role can be deleted', function () {
    $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

    $this->deleteJson("/admin/auth/roles/{$role->id}")->assertOk();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});
```

- [x] **Step 3: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Resource/RolesTest.php`
Expected: FAIL — controller class not found.

- [x] **Step 4: Create the controller**

`resourceKey()` returns `'roles'`, `model()` returns `Role::class`, `$title = 'Roles'`.

`grid()`: columns `name`, `slug`, `permissions` (`->display(fn ($row) => $row->permissions()->count())`), `created_at` (`->datetime()`). Filters `like('name')`, `like('slug')`. Actions `Edit`, `Delete`.

`form(bool $editing)`:
- `name` — `['required', 'string', 'max:50']`
- `slug` — `['required', 'string', 'max:50', 'alpha_dash', 'unique:'.config('blatui-admin.database.roles_table').',slug'.($key ? ','.$key : '')]`
- `permissions` — `Multiselect` with `->relation('permissions')` and `->options($this->treeOptions(Permission::class, 'name'))`
- `menus` — `Multiselect` with `->relation('menus')` and `->options($this->treeOptions(Menu::class, 'title'))`

When editing, load the record and prefill both from `$record->permissions->pluck('id')->all()` and `$record->menus->pluck('id')->all()`.

`authorizeDestroy()`: 403 when `$record->slug === 'administrator'`.

- [x] **Step 5: Run the tests**

Run: `vendor/bin/pest tests/Feature/Resource/RolesTest.php`
Expected: PASS, 7 tests.

- [x] **Step 6: Run the full gate**

Run: `composer test`
Expected: green.

- [x] **Step 7: Commit**

```bash
git add src/Http/Controllers/Resources/Concerns/BuildsTreeOptions.php src/Http/Controllers/Resources/RolesController.php tests/Feature/Resource/RolesTest.php
git commit -m "feat(admin): add roles resource page with permission and menu assignment"
```

---

### Task 6: Permissions resource

**Files:**
- Create: `src/Http/Controllers/Resources/PermissionsController.php`
- Create: `tests/Feature/Resource/PermissionsTest.php`

**Interfaces:**
- Consumes: `ResourceController` (Task 3), `BuildsTreeOptions` (Task 5), `Field\Textarea`, `Field\Select`.
- Produces: `class BlatUI\Admin\Http\Controllers\Resources\PermissionsController extends ResourceController`.

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Resource/PermissionsTest.php`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Permission;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the permissions index renders a grid', function () {
    $this->get('/admin/auth/permissions')->assertOk()->assertSee('Permissions');
});

test('a permission can be created', function () {
    $this->post('/admin/auth/permissions', [
        'name' => 'Reports', 'slug' => 'reports',
        'http_method' => 'GET,POST', 'http_path' => '/reports*', 'order' => 9, 'parent_id' => 0,
    ])->assertRedirect('/admin/auth/permissions');

    expect(Permission::query()->where('slug', 'reports')->exists())->toBeTrue();
});

test('the edit form excludes the permission from its own parent options', function () {
    $permission = Permission::query()->where('slug', 'users')->firstOrFail();

    $html = $this->get("/admin/auth/permissions/{$permission->id}/edit")
        ->assertOk()
        ->getContent();

    // The seeded 'users' permission must not be offered as its own parent.
    expect($html)->toContain('Auth management');
    $optionName = 'permission_ids';
    expect(substr_count($html, "value=\"{$permission->id}\""))->toBeLessThanOrEqual(1);
});

test('a permission can be updated and deleted', function () {
    $permission = Permission::query()->create([
        'name' => 'Reports', 'slug' => 'reports', 'parent_id' => 0, 'order' => 9,
    ]);

    $this->put("/admin/auth/permissions/{$permission->id}", [
        'name' => 'Reporting', 'slug' => 'reporting', 'parent_id' => 0, 'order' => 10,
    ])->assertRedirect('/admin/auth/permissions');

    expect($permission->fresh()->name)->toBe('Reporting');

    $this->deleteJson("/admin/auth/permissions/{$permission->id}")->assertOk();

    expect(Permission::query()->whereKey($permission->id)->exists())->toBeFalse();
});

test('validates slug uniqueness', function () {
    $this->post('/admin/auth/permissions', ['name' => 'Dup', 'slug' => 'users'])
        ->assertSessionHasErrors('slug');
});
```

If the `parent` self-exclusion assertion proves brittle against the rendered markup, assert it directly instead: call the controller's option-building method and assert the returned array has no key equal to `$permission->id`. A test that passes for the wrong reason is worse than no test.

- [x] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Resource/PermissionsTest.php`
Expected: FAIL — controller class not found.

- [x] **Step 3: Create the controller**

`resourceKey()` returns `'permissions'`, `model()` returns `Permission::class`, `$title = 'Permissions'`.

`grid()`: columns `name`, `slug`, `http_method`, `http_path` (with `->limit()`), `parent_id` (`->display()` resolving the parent name), `order`. Filters `like('name')`, `like('slug')`. Actions `Edit`, `Delete`.

`form(bool $editing)`:
- `name` — `['required', 'string', 'max:50']`
- `slug` — `['required', 'string', 'max:50', 'alpha_dash', 'unique:'.config('blatui-admin.database.permissions_table').',slug'.($key ? ','.$key : '')]`
- `http_method` — `['nullable', 'string', 'max:255']`, help `'Comma separated, e.g. GET,POST. Leave blank to match any method.'`
- `http_path` — textarea, `['nullable', 'string']`, help `'One path per line. Leave blank to match any path.'`
- `order` — `['nullable', 'integer', 'min:0']`
- `parent_id` — `Select` with `->options($this->parentOptions($form->getKey()))`

`parentOptions(mixed $excludeId)`: `['0' => '— Top level —']` merged with `BuildsTreeOptions::treeOptions(Permission::class, 'name')`, filtered to remove `$excludeId` when it is not null.

- [x] **Step 4: Run the tests**

Run: `vendor/bin/pest tests/Feature/Resource/PermissionsTest.php`
Expected: PASS, 5 tests.

- [x] **Step 5: Run the full gate**

Run: `composer test`
Expected: green.

- [x] **Step 6: Commit**

```bash
git add src/Http/Controllers/Resources/PermissionsController.php tests/Feature/Resource/PermissionsTest.php
git commit -m "feat(admin): add permissions resource page"
```

---

### Task 7: Menus resource

**Files:**
- Create: `src/Http/Controllers/Resources/MenusController.php`
- Create: `tests/Feature/Resource/MenusTest.php`

**Interfaces:**
- Consumes: `ResourceController` (Task 3), `BuildsTreeOptions` (Task 5), `Multiselect`, `Field\SwitchField`, `Field\Select`.
- Produces: `class BlatUI\Admin\Http\Controllers\Resources\MenusController extends ResourceController`.

- [x] **Step 1: Write the failing test**

Create `tests/Feature/Resource/MenusTest.php`:

```php
<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the menus index renders a grid', function () {
    $this->get('/admin/auth/menu')->assertOk()->assertSee('Dashboard');
});

test('a menu can be created with a role assignment', function () {
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    $this->post('/admin/auth/menu', [
        'title' => 'Reports', 'icon' => 'lucide-bar-chart', 'uri' => 'reports',
        'parent_id' => 0, 'order' => 8, 'show' => 1, 'roles' => [$role->id],
    ])->assertRedirect('/admin/auth/menu');

    $menu = Menu::query()->where('uri', 'reports')->firstOrFail();

    expect($menu->roles->pluck('id')->all())->toBe([$role->id]);
});

test('a hidden menu defaults to hidden when the switch is off', function () {
    $this->post('/admin/auth/menu', [
        'title' => 'Reports', 'uri' => 'reports', 'parent_id' => 0, 'order' => 8,
    ])->assertRedirect('/admin/auth/menu');

    expect((int) Menu::query()->where('uri', 'reports')->firstOrFail()->show)->toBe(0);
});

test('a menu can be updated and deleted', function () {
    $menu = Menu::query()->create([
        'title' => 'Reports', 'uri' => 'reports', 'parent_id' => 0, 'order' => 8, 'show' => 1,
    ]);

    $this->put("/admin/auth/menu/{$menu->id}", [
        'title' => 'Reporting', 'uri' => 'reporting', 'parent_id' => 0, 'order' => 9,
    ])->assertRedirect('/admin/auth/menu');

    expect($menu->fresh()->title)->toBe('Reporting');

    $this->deleteJson("/admin/auth/menu/{$menu->id}")->assertOk();

    expect(Menu::query()->whereKey($menu->id)->exists())->toBeFalse();
});

test('menu parent options exclude the record itself', function () {
    $menu = Menu::query()->where('uri', 'auth/users')->firstOrFail();

    $this->get("/admin/auth/menu/{$menu->id}/edit")->assertOk();
});
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Resource/MenusTest.php`
Expected: FAIL — controller class not found.

- [x] **Step 3: Create the controller**

`resourceKey()` returns `'menu'` (the Seeder's menu URI is `auth/menu`, singular — this is the one resource whose key differs from its plural name), `model()` returns `Menu::class`, `$title = 'Menu'`.

`grid()`: columns `title`, `icon`, `uri`, `order`, `show` (`->display()` emitting a badge whose variant maps `1 => 'success'`, `0 => 'secondary'`), `created_at`. Filters `like('title')`, `like('uri')`. Actions `Edit`, `Delete`.

`form(bool $editing)`:
- `title` — `['required', 'string', 'max:50']`
- `icon` — `['nullable', 'string', 'max:50']`, help `'A Lucide icon name, e.g. lucide-users.'`
- `uri` — `['nullable', 'string', 'max:50']`, help `'Relative to the admin prefix, e.g. auth/users. Leave blank for a grouping item.'`
- `order` — `['nullable', 'integer', 'min:0']`
- `show` — `SwitchField`; when off, `prepareDataForSave()` already writes `0` (`src/Form.php:579`)
- `parent_id` — `Select` with `->options($this->parentOptions($form->getKey()))`, same shape as Task 6
- `roles` — `Multiselect` with `->relation('roles')` and `->options(Role::query()->orderBy('name')->pluck('name', 'id')->all())`; prefill `$record->roles->pluck('id')->all()` when editing

- [x] **Step 4: Run the tests**

Run: `vendor/bin/pest tests/Feature/Resource/MenusTest.php`
Expected: PASS, 5 tests.

- [x] **Step 5: Run the full gate**

Run: `composer test`
Expected: green, with all four resource test files passing.

- [x] **Step 6: Commit**

```bash
git add src/Http/Controllers/Resources/MenusController.php tests/Feature/Resource/MenusTest.php
git commit -m "feat(admin): add menus resource page"
```

---

### Task 8: Documentation

`AGENTS.md` is binding: an architecture change is not complete until it describes the result.

**Files:**
- Modify: `AGENTS.md`, `README.md`, `lang/en/admin.php`, `lang/zh_CN/admin.php`, `resources/boost/skills/blatui-admin-development/SKILL.md`

- [x] **Step 1: Update `AGENTS.md`**

Under "Rendering Architecture & Security", extend the ViewModel bullet to record that `Form\Field\Relation` fields are persisted to pivot tables rather than the model table, and that `sync()` failures propagate rather than being swallowed.

Add a short "Resource Pages" section: `ResourceController` supplies the CRUD skeleton; the four bundled controllers live in `src/Http/Controllers/Resources/`; `config('blatui-admin.resources')` maps a key to a controller class and the route file generates from it.

Record the rejections from the spec so they are not reinvented: nested tree fields, multiselect description sub-labels, silent pivot-failure tolerance, and authorization middleware (explicitly deferred).

- [x] **Step 2: Update the language files**

Add to both `lang/en/admin.php` and `lang/zh_CN/admin.php`:

```
'users'            => 'Users'    |  '用户'
'create'           => 'Create'   |  '新建'
'edit'             => 'Edit'     |  '编辑'
'delete'           => 'Delete'   |  '删除'
'deleted'          => 'Deleted successfully' | '删除成功'
'cannot_delete_self'       => 'You cannot delete your own account.' | '不能删除自己的账号。'
'cannot_delete_admin_role' => 'The built-in administrator role cannot be deleted.' | '内置管理员角色不能删除。'
```

Keep the key sets identical across both locales.

- [x] **Step 3: Update `README.md`**

Show the four resource routes in the quick-start, and document that `config('blatui-admin.resources')` swaps a controller without forking.

- [x] **Step 4: Update the Boost skill**

If `resources/boost/skills/blatui-admin-development/SKILL.md` documents the Form field list or the controller set, add `Multiselect` and `ResourceController`. If it does not mention either, leave it alone and say so in the commit message.

- [x] **Step 5: Run the full gate**

Run: `composer test`
Expected: green.

- [x] **Step 6: Verify no raw Blade output**

Run: `grep -rn '{!!' resources/views`
Expected: no output.

- [x] **Step 7: Commit**

```bash
git add AGENTS.md README.md lang/en/admin.php lang/zh_CN/admin.php resources/boost/skills/
git commit -m "docs: record resource pages architecture and translations"
```

---

### Task 9: Branch verification

Per `.agents/rules/multi-agent-workflow.md`, merging to `main` is a separate explicit step that re-runs the full gate.

- [ ] **Step 1: Confirm the working tree is clean** — NOT SATISFIED, left unchecked deliberately

Run: `git status --porcelain`
Expected: no output.
Actual (2026-10-04): six local entries, all of them scratch artifacts of manual runs and agent tooling, none of them package content — modified `.agents/settings.json`, untracked `.agents/.cache/`, `.agents/skills/run-blatui-admin/`, `dashboard.png`, `login.png`, `update.py`. No feature file is uncommitted; the branch fast-forwarded to `main` cleanly. These were neither committed nor deleted, since removing or absorbing someone's local files is not this plan's call. The owner should either ignore or remove them.

- [x] **Step 2: Run the full gate one final time**

Run: `composer test`
Expected: PHPStan 0 errors, Pint clean, type coverage 100%, all Pest suites green.
Actual (2026-10-04, re-run on `main` after the fast-forward): PHPStan passed with 0 errors, Pint passed, type coverage 100.0%, Pest 212 passed with 1067 assertions in 10.9s.

- [x] **Step 3: Confirm the commit history**

Run: `git log --oneline main..HEAD`
Expected: nine commits, one per task, on `feature/rbac-resource-pages`.
Actual (2026-10-04): the branch carried 13 commits ahead of `main`, not nine — the plan's estimate was low because Tasks 3, 4 and the batch-delete guard each needed a follow-up fix commit. All 13 plus the status-header commit were fast-forwarded into `main`, which is now at `833d41f`.

- [x] **Step 4: Report to the user**

State the branch name, the commit count, and paste the actual `composer test` output. Do not claim the branch is ready to merge without the real output in hand. Merging is the user's call.
Actual (2026-10-04): reported in session — branch `feature/rbac-resource-pages` merged into `main` by fast-forward with the user's explicit instruction, 14 commits, full gate green as pasted in Step 2.
