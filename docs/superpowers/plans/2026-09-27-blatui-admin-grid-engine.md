# BlatUI Admin Grid Engine Implementation Plan

> **Status: COMPLETE (verified 2026-09-28).** The planned deliverables are present: `src/Contracts/Repository.php`, the full `src/Grid/Displayers/` set (Badge, Copyable, Datetime, Image, Limit, Link, Using), and the filter classes under `src/Grid/Filter/`. Retained for reference; no further work is pending here.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the high-performance BlatUI Grid data table engine (`Grid`, `Repository`, `Column`, `Displayers`, `Row`, `RowAction`, `Filter`, `Tools`, `BatchActions`), replace Dcat's jQuery/Pjax with BlatUI Blade + Tailwind CSS v4 + Alpine.js + Fetch API, and provide seamless `Responsable` controller integration.

**Architecture:** Model-Repository pattern with `BlatUI\Admin\Contracts\Repository` and `EloquentRepository`, coordinated by `Grid\Model` for query building, sorting, filtering, and pagination. Clean column definition DSL with chainable displayers (`badge`, `link`, `copyable`, `image`, `datetime`, `using`). Modern Tailwind CSS v4 data table with Alpine-driven row selection, batch actions, search filters, and AJAX delete actions with Sonner feedback.

**Tech Stack:** PHP ^8.3, Laravel ^12.0 || ^13.0, Eloquent ORM, Blade, Alpine.js, Tailwind CSS v4, Fetch API, Pest, PHPStan Level 7, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-09-27-blatui-admin-design.md`

## Global Constraints

- Package namespace must be strictly `BlatUI\Admin`.
- View namespace must be `blatui-admin` (e.g. `blatui-admin::grid.table`).
- Zero jQuery, zero Bootstrap, zero Livewire runtime dependencies.
- Frontend interactions must rely exclusively on Alpine.js directives, Fetch API, and Tailwind CSS v4 styling.
- Strictly adhere to PHP 8.3+ with `declare(strict_types=1);` and 100% type coverage.
- Code style must pass `vendor/bin/pint --test` and analysis must pass `vendor/bin/phpstan analyse`.

## Review Focus

- **Model vs Repository Flexibility**: Grid must accept an Eloquent Model instance (`new User`), a Model class string (`User::class`), an Eloquent Query Builder (`User::query()`), or a custom `Repository` implementation.
- **Async Action CSRF & JSON Contract**: Row delete and batch delete actions must include CSRF tokens, send `X-Requested-With: XMLHttpRequest`, and expect `{ status: true, message: '...' }` responses.
- **Search Query Parameter Preservation**: Sorting and pagination links must preserve active filter query parameters without duplication.
- **Displayer Graceful Fallback**: Custom column displayers (e.g., `image`, `badge`, `datetime`) must handle null or empty values gracefully without PHP warnings.
- **Grid Responsable Contract**: A controller returning a `Grid` directly must automatically render inside `Content` layout with HTTP 200 HTML response.

---

### Task 1: Repository Contract & Eloquent Repository

**Files:**
- Create: `src/Contracts/Repository.php`
- Create: `src/Repositories/EloquentRepository.php`
- Test: `tests/Unit/RepositoryTest.php`

**Interfaces:**
- Consumes: Eloquent `Model`, `Builder`, `Collection`, `LengthAwarePaginator`
- Produces: `BlatUI\Admin\Contracts\Repository` interface and `EloquentRepository` class.

- [ ] **Step 1: Write the failing test for Repository**

```php
// tests/Unit/RepositoryTest.php
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Repositories\EloquentRepository;
use Database\Seeders\AdminTablesSeeder;

test('eloquent repository retrieves model metadata and queries records', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $repository = new EloquentRepository(new Administrator());

    expect($repository->getKeyName())->toBe('id')
        ->and($repository->getCreatedAtColumn())->toBe('created_at')
        ->and($repository->isSoftDeletes())->toBeFalse();

    $admin = $repository->edit(1);
    expect($admin)->not->toBeNull()
        ->and($admin->username)->toBe('admin');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/RepositoryTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Repository contract and EloquentRepository**

1. `src/Contracts/Repository.php`:
   - `getKeyName(): string`
   - `getCreatedAtColumn(): ?string`
   - `getUpdatedAtColumn(): ?string`
   - `isSoftDeletes(): bool`
   - `model(): mixed`
   - `edit(mixed $key): mixed`
   - `update(mixed $key, array $values): bool`
   - `destroy(mixed $key): bool`
2. `src/Repositories/EloquentRepository.php`:
   - Wraps Eloquent `Model` or `Builder`.
   - Implements all `Repository` methods cleanly.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/RepositoryTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Contracts/Repository.php src/Repositories/EloquentRepository.php tests/Unit/RepositoryTest.php
git commit -m "feat(grid): implement Repository contract and EloquentRepository"
```

---

### Task 2: Grid Model & Data Query Coordinator

**Files:**
- Create: `src/Grid/Model.php`
- Test: `tests/Unit/GridModelTest.php`

**Interfaces:**
- Consumes: `BlatUI\Admin\Contracts\Repository`, `Illuminate\Pagination\LengthAwarePaginator`
- Produces: `BlatUI\Admin\Grid\Model` for managing query building, sorting, eager-loading, and pagination.

- [ ] **Step 1: Write the failing test for Grid Model**

```php
// tests/Unit/GridModelTest.php
use BlatUI\Admin\Grid\Model as GridModel;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('grid model manages queries, sorting and pagination', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $gridModel = new GridModel(new Administrator());
    $gridModel->setPerPage(10);
    $gridModel->orderBy('id', 'desc');

    $records = $gridModel->buildData();

    expect($records)->toHaveCount(1)
        ->and($records->first()->username)->toBe('admin');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/GridModelTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Grid\Model**

1. Create `src/Grid/Model.php`:
   - Accepts Eloquent `Model`, `Builder`, class string, or `Repository`.
   - Manages pagination settings (`$perPage`, `$perPageName`, `$pageName`).
   - Handles eager relations (`with([...])`).
   - Handles sorting via request queries (`_sort[column]=desc`) or programmatic `orderBy($column, $direction)`.
   - Integrates with Filter criteria.
   - Implements `buildData(): LengthAwarePaginator|Collection`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/GridModelTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Grid/Model.php tests/Unit/GridModelTest.php
git commit -m "feat(grid): implement Grid Model query and pagination coordinator"
```

---

### Task 3: Grid Column & Displayers

**Files:**
- Create: `src/Grid/Column.php`
- Create: `src/Grid/Displayers/Badge.php`
- Create: `src/Grid/Displayers/Link.php`
- Create: `src/Grid/Displayers/Copyable.php`
- Create: `src/Grid/Displayers/Image.php`
- Create: `src/Grid/Displayers/Datetime.php`
- Create: `src/Grid/Displayers/Using.php`
- Test: `tests/Unit/GridColumnTest.php`

**Interfaces:**
- Consumes: Blade helpers, String manipulation
- Produces: Fluent column DSL (`column('id')->sortable()`, `column('status')->badge()`, `column('avatar')->image()`).

- [ ] **Step 1: Write the failing test for Column and Displayers**

```php
// tests/Unit/GridColumnTest.php
use BlatUI\Admin\Grid\Column;

test('grid column supports fluent label, sorting and custom displayers', function () {
    $column = new Column('status', 'Account Status');
    $column->sortable();
    $column->badge('success', [1 => 'Active', 0 => 'Disabled']);

    expect($column->getName())->toBe('status')
        ->and($column->getLabel())->toBe('Account Status')
        ->and($column->isSortable())->toBeTrue();

    $rendered = $column->renderCell(1, ['id' => 1, 'status' => 1]);
    expect($rendered)->toContain('Active');
});

test('grid column supports closures, copyable and datetime displayers', function () {
    $column = new Column('created_at', 'Created');
    $column->datetime('Y-m-d');

    $html = $column->renderCell('2026-09-27 12:00:00', []);
    expect($html)->toContain('2026-09-27');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/GridColumnTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Column and Displayers**

1. Create `src/Grid/Column.php` with fluent chaining:
   - `label(string $label)`
   - `sortable(bool $sortable = true)`
   - `display(Closure $callback)`
   - Displayer methods: `badge()`, `link()`, `copyable()`, `image()`, `datetime()`, `using()`, `limit()`.
2. Create displayer implementations in `src/Grid/Displayers/`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/GridColumnTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Grid/Column.php src/Grid/Displayers/ tests/Unit/GridColumnTest.php
git commit -m "feat(grid): implement Column DSL and formatting displayers"
```

---

### Task 4: Row, Row Actions & AJAX Delete

**Files:**
- Create: `src/Grid/Row.php`
- Create: `src/Grid/RowAction.php`
- Create: `src/Grid/Actions/Show.php`
- Create: `src/Grid/Actions/Edit.php`
- Create: `src/Grid/Actions/Delete.php`
- Create: `src/Grid/Actions/QuickEdit.php`
- Test: `tests/Unit/GridRowTest.php`

**Interfaces:**
- Consumes: `Grid`, `Grid\Column`, Fetch API interaction
- Produces: Per-record row abstraction with action links (show, edit, delete with confirmation).

- [ ] **Step 1: Write the failing test for Row and Row Actions**

```php
// tests/Unit/GridRowTest.php
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('grid row renders cells and actions', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $admin = Administrator::where('username', 'admin')->first();
    $row = new Row($admin, 0);

    expect($row->getKey())->toBe(1);
    expect($row->renderActions())->toContain('Edit', 'Delete');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/GridRowTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Row, RowAction, and standard Actions**

1. `src/Grid/Row.php`: stores model/record, row index, and row actions.
2. `src/Grid/RowAction.php`: base class for row actions.
3. `src/Grid/Actions/Show.php`: renders link to resource show route.
4. `src/Grid/Actions/Edit.php`: renders link to resource edit route.
5. `src/Grid/Actions/Delete.php`: renders Alpine-powered delete trigger with confirmation prompt, executing Fetch `DELETE` and triggering Sonner toast upon success.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/GridRowTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Grid/Row.php src/Grid/RowAction.php src/Grid/Actions/ tests/Unit/GridRowTest.php
git commit -m "feat(grid): implement Row and RowActions with AJAX delete"
```

---

### Task 5: Grid Filter, Tools & Batch Actions

**Files:**
- Create: `src/Grid/Filter.php`
- Create: `src/Grid/Filter/Field.php`
- Create: `src/Grid/Tools.php`
- Create: `src/Grid/BatchAction.php`
- Create: `src/Grid/Tools/BatchDelete.php`
- Test: `tests/Feature/GridFilterToolsTest.php`

**Interfaces:**
- Consumes: Query request parameters, BlatUI inputs
- Produces: Search filters, toolbar controls, and batch actions.

- [ ] **Step 1: Write the failing test for Filter and Tools**

```php
// tests/Feature/GridFilterToolsTest.php
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Tools;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('grid filter builds conditions and filters records', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $filter = new Filter(new Administrator());
    $filter->equal('id', 'ID');
    $filter->like('username', 'Username');

    expect($filter->getFields())->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/GridFilterToolsTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Filter, Tools, and BatchActions**

1. `src/Grid/Filter.php`: manages query condition clauses (`equal`, `like`, `gt`, `lt`, `between`, `in`) and applies them to query.
2. `src/Grid/Tools.php`: manages toolbar elements (create button, reload button, batch actions, filter button).
3. `src/Grid/BatchAction.php` and `src/Grid/Tools/BatchDelete.php`: handles batch operations with selected IDs.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/GridFilterToolsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Grid/Filter.php src/Grid/Filter/ src/Grid/Tools.php src/Grid/BatchAction.php src/Grid/Tools/ tests/Feature/GridFilterToolsTest.php
git commit -m "feat(grid): implement Filter, Tools and BatchAction controls"
```

---

### Task 6: Grid Core, BlatUI Table Views & Responsable Controller Integration

**Files:**
- Create: `src/Grid.php`
- Create: `resources/views/grid/table.blade.php`
- Create: `resources/views/grid/filter.blade.php`
- Create: `resources/views/grid/pagination.blade.php`
- Test: `tests/Feature/GridFeatureTest.php`

**Interfaces:**
- Consumes: `Grid\Model`, `Grid\Column`, `Grid\Row`, `Grid\Filter`, `Grid\Tools`, `Content`
- Produces: Complete Grid component implementing `Renderable, Htmlable, Responsable`.

- [ ] **Step 1: Write the failing feature test for Grid**

```php
// tests/Feature/GridFeatureTest.php
use BlatUI\Admin\Grid;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('grid compiles complete table with data, columns and tools', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $grid = Grid::make(new Administrator(), function (Grid $grid) {
        $grid->column('id', 'ID')->sortable();
        $grid->column('username', 'Username')->badge();
        $grid->column('name', 'Name');
        $grid->column('created_at', 'Created At')->datetime();
    });

    $html = $grid->render();

    expect($html)->toContain('ID')
        ->and($html)->toContain('Username')
        ->and($html)->toContain('admin')
        ->and($html)->toContain('Super Administrator');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/GridFeatureTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Grid and Blade views**

1. `src/Grid.php`:
   - Implements `Renderable, Htmlable, Responsable`.
   - Assembles `Model`, `Columns`, `Filter`, `Tools`, `Rows`.
   - `render()` passes prepared dataset to `blatui-admin::grid.table`.
   - `toResponse($request)` wraps the rendered Grid inside `Content::make()` layout!
2. `resources/views/grid/table.blade.php`:
   - Modern Tailwind CSS v4 data table.
   - Alpine.js `x-data="{ selectedRows: [], allSelected: false }"` checkbox management.
   - Responsive scroll container, hover states, empty state card.
3. `resources/views/grid/filter.blade.php`:
   - Collapsible filter card.
4. `resources/views/grid/pagination.blade.php`:
   - Paginator with current range, total count, and preserved query strings.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/GridFeatureTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Grid.php resources/views/grid/ tests/Feature/GridFeatureTest.php
git commit -m "feat(grid): implement Grid core DSL and BlatUI table views"
```

---

### Task 7: Full Verification, Quality Gate & Branch Merge

**Files:**
- Modify: Any files needing linting, type adjustments, or documentation.
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

- [ ] **Step 4: Commit quality verification**
```bash
git add .
git commit -m "chore: verify grid engine test suite and code quality"
```

- [ ] **Step 5: Merge feature branch into main and push to remote**
```bash
git checkout main
git merge feature/grid-engine
composer test
git push origin main
```
