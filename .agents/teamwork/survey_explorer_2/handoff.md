# Codebase Views & Composers Investigation Report (R2, R3, R4)

## 1. Observation

### 1.1 Existing Blade Views & `{!! !!}` Usages
A full ripgrep across `resources/views/` revealed the following raw `{!! !!}` occurrences:
- `resources/views/grid/table.blade.php`:
  - Line 32: `{!! $filter->render() !!}`
  - Line 37: `{!! $tools->render() !!}`
  - Line 106: `{!! $row->renderCheckbox() !!}`
  - Line 112: `{!! $row->cell($column) !!}`
  - Line 118: `{!! $row->renderActions() !!}`
- `resources/views/grid/filter.blade.php`:
  - Line 47: `{!! $field->render() !!}`
- `resources/views/layouts/app.blade.php`:
  - Line 87: `{!! is_string($content) ? $content : $content->renderRows() !!}`
- `resources/views/layouts/fallback.blade.php`:
  - Line 12: `{!! $row->render() !!}` (renders `\BlatUI\Admin\Layout\Row`)
- `resources/views/partials/header.blade.php`:
  - Line 37: `{!! $navbarLeft !!}`
  - Line 68: `{!! $navbarRight !!}`

### 1.2 Component Contract Implementation State
- `src/Grid/Filter.php`: Lines 24-25: `class Filter implements Htmlable, Renderable, Stringable`. Already implements `Htmlable` and has `toHtml(): string` (line 424).
- `src/Grid/Tools.php`: Lines 13-14: `class Tools implements Htmlable, Renderable, Stringable`. Already implements `Htmlable` and has `toHtml(): string` (line 472).
- `src/Grid/Filter/Field.php`: Line 15: `abstract class Field implements Htmlable, Renderable, Stringable`. Already implements `Htmlable` and has `toHtml(): string` (line 335).
- `src/Grid/Column.php`: Line 15: `class Column` does NOT implement `Htmlable`. Cell values are rendered via `Column::renderCell()` (lines 310-370) which returns `string`, with scalar values escaped via `htmlspecialchars((string) $current, ENT_QUOTES, 'UTF-8')`.
- `src/Grid/Row.php`: Line 21: `class Row implements Arrayable`. Does NOT implement `Htmlable`.
  - Line 525: `public function cell(Column $column): string` returns raw `string`, not `HtmlString`.
  - Line 509: `public function renderCheckbox(string $name = '_row_id'): string` returns raw HTML string.
  - Line 415: `public function renderActions(): string` returns raw HTML string.
- `src/Layout/Content.php`: Line 16: `class Content implements Htmlable, Renderable, Responsable`.
  - Line 239: passes `'content' => $this->renderRows()`, which is a raw `string` of HTML.
- `src/Grid.php`:
  - Lines 553-574: creates `$toolsProxy = new class($this->tools)` which implements `render()`, `__call()`, `__toString()`, but DOES NOT implement `\Illuminate\Contracts\Support\Htmlable`.
  - Lines 576-597: creates `$filterProxy = new class($this->filter)` which implements `render()`, `__call()`, `__toString()`, but DOES NOT implement `\Illuminate\Contracts\Support\Htmlable`.
  - Line 604-605: passes `'tools' => $toolsProxy` and `'filter' => $filterProxy` to `view('blatui-admin::grid.table')`.

### 1.3 View Directory Structure & AdminServiceProvider
- Existing view classes in `src/`: `src/View/` does NOT exist. There is no `src/View/Composers/` directory.
- `src/AdminServiceProvider.php`:
  - Line 41: `$this->loadViewsFrom(__DIR__.'/../resources/views', 'blatui-admin');`
  - Currently no `View::composer(...)` calls are registered in `boot()`.

### 1.4 Test Suite Baseline
- `vendor/bin/pest`: 107 tests total, 106 passed, 1 failed (`tests/Unit/GridColumnTest.php:465` asserting `Column` implements `Htmlable`, added as part of Task 2 red phase).
- `vendor/bin/phpstan analyse`: passed with 0 errors.
- `vendor/bin/pint --test`: 1 formatting issue in `tests/Unit/GridColumnTest.php`.

### 1.5 Current `AGENTS.md`
- Total 59 lines. Contains: Package Conventions (lines 5-11), Quick Commands (lines 13-20), Local Skills (lines 22-28), Context7 MCP (lines 30-41), and OpenWiki (lines 43-58). No section currently exists for Rendering Architecture or Security Guidelines.

---

## 2. Logic Chain

1. **Blade Escaping Mechanism**: In Laravel Blade, `{{ $expression }}` compiles to `<?php echo e($expression); ?>`. The `e()` helper checks `if ($value instanceof Htmlable) { return $value->toHtml(); }`. For any value not implementing `Htmlable`, `e()` applies `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', double_encode: true)`.
2. **Double-Encoding Trap on Pre-Rendered Components**:
   - If `$toolsProxy` and `$filterProxy` in `src/Grid.php` do not implement `Htmlable`, changing `table.blade.php` to `{{ $tools }}` and `{{ $filter }}` will cause `e()` to cast them to string via `__toString()` and escape `<` and `>` into `&lt;` and `&gt;`. This was verified experimentally: `php -r "$obj = new class { public function __toString() { return '<div>hello</div>'; } }; echo e($obj);"` outputs `&lt;div&gt;hello&lt;/div&gt;`.
   - Therefore, `$toolsProxy` and `$filterProxy` MUST implement `\Illuminate\Contracts\Support\Htmlable` (or `Grid::render()` must pass `$this->tools` and `$this->filter` directly).
3. **Row::cell() Contract Requirement**:
   - In `src/Grid/Row.php`, `Row::cell(Column $column)` currently returns `string`.
   - When `table.blade.php` is modernized from `{!! $row->cell($column) !!}` to `{{ $row->cell($column) }}`, if `cell()` returns a plain `string`, any HTML produced by displayers (e.g. badges, links, images) will be double-escaped into HTML entities.
   - By changing `Row::cell()` to return `\Illuminate\Support\HtmlString`, Blade's `e()` recognizes the `Htmlable` contract and does not escape the displayer HTML. Plain values are already escaped inside `Column::renderCell()` via `htmlspecialchars`, ensuring zero XSS risk and zero double-escaping.
4. **Layout Content Escaping in `layouts/app.blade.php`**:
   - In `resources/views/layouts/app.blade.php` line 87, replacing `{!! is_string($content) ? $content : $content->renderRows() !!}` with `{{ $content }}` requires `$content` to be `Htmlable`.
   - In `src/Layout/Content.php` line 239, `'content' => $this->renderRows()` passes a raw HTML string. If passed as a plain string to `{{ $content }}`, all layout row markup is escaped into entities.
   - Therefore, `Content::render()` MUST pass `'content' => new \Illuminate\Support\HtmlString($this->renderRows())`.
5. **ViewComposer Defensive Gatekeeper Mechanics**:
   - In `LayoutComposer` and `GridComposer`, the composer intercepts view data before Blade processes it.
   - If a variable implements `Htmlable` (e.g. `$content`, `$tools`, `$filter`, `$grid`), it is an intentional safe component and must be preserved without modification.
   - If a variable is a plain scalar `string` (e.g. `$title`, `$description`, or user-supplied variables passed via `with()`), it represents unverified input.
   - If the Composer sanitizes the string using `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` and wraps it in `new \Illuminate\Support\HtmlString(...)`, two critical security invariants are achieved:
     1. In modern Blade templates using `{{ $title }}`, `HtmlString` prevents double-encoding (`&amp;lt;`).
     2. In legacy or third-party templates using `{!! $title !!}`, the string is already sanitized to `&lt;script&gt;`, neutralising XSS attacks even if raw tags are used.
6. **ServiceProvider Wiring**:
   - Registering `GridComposer` for `blatui-admin::grid.table` and `blatui-admin::grid.filter`, and `LayoutComposer` for `blatui-admin::layouts.app` and `blatui-admin::layouts.fallback` in `AdminServiceProvider::boot()` ensures all top-level view entries are guarded.

---

## 3. Caveats

1. **Anonymous Proxies in `Grid.php`**:
   `src/Grid.php` creates `$toolsProxy` and `$filterProxy` as anonymous classes wrapping `$this->tools` and `$this->filter`. If these proxies are not given `implements \Illuminate\Contracts\Support\Htmlable` with `public function toHtml(): string { return $this->tools->toHtml(); }` (and `$this->filter->toHtml()`), modernizing `table.blade.php` to `{{ $tools }}` and `{{ $filter }}` will corrupt UI rendering into escaped HTML text.
2. **Pest Assertions on `Row::cell()`**:
   In `tests/Unit/GridRowTest.php:63`, the test currently asserts `expect($row->cell($col1))->toBe('administrator')`. If `cell()` returns `HtmlString`, `toBe()` (strict `===`) will fail because an `HtmlString` object is not strictly equal to a string primitive. The test must be updated to `expect((string) $row->cell($col1))->toBe('administrator')` or `expect($row->cell($col1)->toHtml())->toBe('administrator')`, along with asserting `expect($row->cell($col1))->toBeInstanceOf(\Illuminate\Support\HtmlString::class)`.
3. **Scope of Raw Tags in `table.blade.php`**:
   `table.blade.php` also contains `{!! $row->renderCheckbox() !!}` (line 106) and `{!! $row->renderActions() !!}` (line 118). The requirement R2 specifically targets `{!! $tools->render() !!}`, `{!! $filter->render() !!}`, `{!! $row->cell($column) !!}`, and `{!! $content !!}`. Unless `renderCheckbox()` and `renderActions()` are updated to return `HtmlString`, changing them to `{{ }}` would escape their HTML markup.
4. **Formatting Violations**:
   `tests/Unit/GridColumnTest.php` currently has a Pint formatting violation from recent red-phase additions. `vendor/bin/pint` must be run after changes.

---

## 4. Conclusion & Required Changes Specification

### 4.1 R2: Blade View Layer Modernization (Task 3)

#### A. `resources/views/grid/table.blade.php`
- **Line 32**:
  ```blade
  {{-- Before --}}
  @if ($filter && ! $grid->isFilterDisabled())
      {!! $filter->render() !!}
  @endif

  {{-- After --}}
  @if ($filter && ! $grid->isFilterDisabled())
      {{ $filter }}
  @endif
  ```
- **Line 37**:
  ```blade
  {{-- Before --}}
  @if ($tools)
      {!! $tools->render() !!}
  @endif

  {{-- After --}}
  @if ($tools)
      {{ $tools }}
  @endif
  ```
- **Line 112**:
  ```blade
  {{-- Before --}}
  @foreach ($columns as $column)
      <td class="px-4 py-3 text-{{ $column->getAlign() }} text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
          {!! $row->cell($column) !!}
      </td>
  @endforeach

  {{-- After --}}
  @foreach ($columns as $column)
      <td class="px-4 py-3 text-{{ $column->getAlign() }} text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
          {{ $row->cell($column) }}
      </td>
  @endforeach
  ```

#### B. `resources/views/grid/filter.blade.php`
- **Line 47**:
  ```blade
  {{-- Before --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4">
      @foreach ($fields as $field)
          {!! $field->render() !!}
      @endforeach
  </div>

  {{-- After --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4">
      @foreach ($fields as $field)
          {{ $field }}
      @endforeach
  </div>
  ```

#### C. `resources/views/layouts/app.blade.php`
- **Line 87**:
  ```blade
  {{-- Before --}}
  @if (isset($content))
      {!! is_string($content) ? $content : $content->renderRows() !!}
  @endif

  {{-- After --}}
  @if (isset($content))
      {{ $content }}
  @endif
  ```

#### D. Supporting PHP Changes for R2
1. **`src/Layout/Content.php` Line 239**:
   ```php
   // Change:
   'content' => $this->renderRows(),
   // To:
   'content' => new \Illuminate\Support\HtmlString($this->renderRows()),
   ```
2. **`src/Grid.php` Lines 553-597**:
   Add `implements \Illuminate\Contracts\Support\Htmlable` to both `$toolsProxy` and `$filterProxy` with `toHtml(): string` methods delegating to `$this->tools->toHtml()` and `$this->filter->toHtml()`.

---

### 4.2 R3: ViewComposer Defensive Gatekeepers (Task 4)

#### A. Directory Creation
- Create directory: `src/View/Composers/`

#### B. `src/View/Composers/GridComposer.php`
```php
<?php

declare(strict_types=1);

namespace BlatUI\Admin\View\Composers;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class GridComposer
{
    /**
     * Bind sanitized data to the grid view.
     */
    public function compose(View $view): void
    {
        foreach ($view->getData() as $key => $value) {
            if ($value instanceof Htmlable) {
                continue;
            }

            if (is_string($value)) {
                $view->with($key, new HtmlString(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
            }
        }
    }
}
```

#### C. `src/View/Composers/LayoutComposer.php`
```php
<?php

declare(strict_types=1);

namespace BlatUI\Admin\View\Composers;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class LayoutComposer
{
    /**
     * Bind sanitized data to the layout view.
     */
    public function compose(View $view): void
    {
        foreach ($view->getData() as $key => $value) {
            if ($value instanceof Htmlable) {
                continue;
            }

            if (is_string($value)) {
                $view->with($key, new HtmlString(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
            }
        }
    }
}
```

#### D. `src/AdminServiceProvider.php` Wiring
Add ViewComposer registration in `boot()`:
```php
use BlatUI\Admin\View\Composers\GridComposer;
use BlatUI\Admin\View\Composers\LayoutComposer;
use Illuminate\Support\Facades\View;

public function boot(): void
{
    $this->loadRoutesFrom(__DIR__.'/../routes/blatui-admin.php');
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'blatui-admin');
    $this->loadTranslationsFrom(__DIR__.'/../lang', 'blatui-admin');
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    $this->registerViewComposers();

    if (! $this->app->runningInConsole()) {
        return;
    }
    ...
}

protected function registerViewComposers(): void
{
    View::composer([
        'blatui-admin::grid.table',
        'blatui-admin::grid.filter',
    ], GridComposer::class);

    View::composer([
        'blatui-admin::layouts.app',
        'blatui-admin::layouts.fallback',
    ], LayoutComposer::class);
}
```

#### E. Test Verification in `tests/Security/XssRenderingTest.php`
Add tests asserting that `GridComposer` and `LayoutComposer` sanitize injected scalar strings:
```php
test('LayoutComposer sanitizes injected scalar strings in layout views', function () {
    $view = view('blatui-admin::layouts.app', [
        'title' => '<script>alert("title")</script>',
        'description' => '<script>alert("desc")</script>',
    ]);

    $html = $view->render();

    expect($html)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;')
        ->toContain('&lt;script&gt;alert(&quot;desc&quot;)&lt;/script&gt;');
});

test('GridComposer sanitizes injected scalar strings in grid views', function () {
    $view = view('blatui-admin::grid.table', [
        'custom_scalar' => '<script>alert("grid-xss")</script>',
    ]);

    $data = $view->getData();
    expect((string) $data['custom_scalar'])
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(&quot;grid-xss&quot;)&lt;/script&gt;');
});
```

---

### 4.3 R4: Architectural Documentation Update (Task 5)

In `AGENTS.md`, insert the following section immediately following `## Package Conventions`:

```markdown
## Rendering Architecture & Security Guidelines

This package enforces a strict rendering architecture to eliminate XSS risks and maintain alignment with modern Laravel standards:

- **ViewModel Pattern**: PHP classes (`Content`, `Grid`, `Filter`, `Tools`, `Row`, `Column`) function strictly as ViewModels and Data Transfer Objects (DTOs). Never concatenate HTML strings inside PHP classes. DOM structures, Tailwind CSS utility classes, and Alpine.js directives belong exclusively in `resources/views/` Blade templates.
- **Native Laravel Contracts (`Htmlable`)**: All visual and UI components (`Content`, `Grid`, `Filter`, `Tools`, `Column`, `Row`, and filter `Field`) must implement `\Illuminate\Contracts\Support\Htmlable`.
- **Blade Escaping Standards**: Never use raw `{!! !!}` for components or dynamic variables. Use native Blade escaping `{{ $component }}`. Laravel's `e()` helper natively recognizes `Htmlable` instances and renders their `toHtml()` output safely without escaping, while non-Htmlable values are escaped against XSS.
- **Cell Rendering & Displayers**: `Row::cell(Column $column)` returns `\Illuminate\Support\HtmlString`. Column displayers (badges, links, images, datetime, etc.) produce pre-escaped, safe HTML; raw scalar cell values are escaped prior to packaging into `HtmlString`.
- **ViewComposer Gatekeepers**: Top-level views (`blatui-admin::layouts.*` and `blatui-admin::grid.*`) are gatekept by `ViewComposer` classes (`LayoutComposer` and `GridComposer`) registered in `AdminServiceProvider`. Composers sanitize and validate scalar strings before template rendering as defense-in-depth against template injection.
```

---

## 5. Verification Method

1. **Unit & Feature Test Execution**:
   - `vendor/bin/pest tests/Security/XssRenderingTest.php`
   - `vendor/bin/pest tests/Feature/GridFeatureTest.php`
   - `vendor/bin/pest tests/Feature/GridFilterToolsTest.php`
   - `vendor/bin/pest tests/Unit/GridRowTest.php`
   - `vendor/bin/pest tests/Unit/GridColumnTest.php`
   - `vendor/bin/pest` (complete suite, ensuring 0 failures)
2. **Static Analysis**:
   - `vendor/bin/phpstan analyse` (verify 0 errors with 100% type coverage)
3. **Code Style & Formatting**:
   - `vendor/bin/pint --test` (verify 0 formatting violations)
4. **File Inspection**:
   - Inspect `resources/views/grid/table.blade.php`, `resources/views/grid/filter.blade.php`, and `resources/views/layouts/app.blade.php` to ensure zero unintended `{!! !!}` remain.
   - Inspect `src/AdminServiceProvider.php` to confirm `registerViewComposers()` registration.
   - Inspect `AGENTS.md` to confirm the presence of `## Rendering Architecture & Security Guidelines`.
