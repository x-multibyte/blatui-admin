# Specification Investigation & Mining Report: Rendering Architecture Refactoring

**Project:** BlatUI Admin (`x-multibyte/blatui-admin`)  
**Branch:** `feature/security-hardening`  
**Milestone:** Survey & Specification Mining  
**Author:** Specification Miner (`survey_spec_miner_1`)  
**Date:** 2026-09-27  

---

## 1. Observation

Direct observations from the authoritative specifications, codebase files, test suites, and git history:

1. **Authoritative Specification & Planning Documents**:
   - `docs/superpowers/specs/2026-09-27-rendering-architecture-design.md`:
     - Line 23: PHP base classes must act as pure DTOs/ViewModels without generating raw HTML DOM string concatenation; DOM, Tailwind, and Alpine.js must reside in Blade views under `resources/views/`.
     - Lines 27-28: Custom UI blocks (`Grid\Filter`, `Grid\Tools`, `Column\Displayer`, etc.) must implement `Illuminate\Contracts\Support\Htmlable`. Blade views must migrate from `{!! $component->render() !!}` to `{{ $component }}`.
     - Lines 32: IoC gatekeepers via `ViewComposer` (registered in `AdminServiceProvider`) to validate and deeply sanitize variables (e.g. `$title`, `$description`) before reaching top-level layouts.
   - `docs/superpowers/plans/2026-09-27-rendering-architecture-refactor.md`:
     - Task 1: Core Layout Refactor completed in commit `e248609` (`src/Layout/Content.php`, `resources/views/layouts/fallback.blade.php`, `tests/Security/XssRenderingTest.php`).
     - Task 2: Implement `Htmlable` on `Filter`, `Tools`, `Column`, and `Row`. Refactor `Row::cell()` to return `HtmlString`. Ensure displayer outputs remain safe and unescaped inside HTML strings while plain values are escaped.
     - Task 3: Modernize views `resources/views/grid/table.blade.php`, `resources/views/grid/filter.blade.php`, and `resources/views/layouts/app.blade.php`, replacing raw `{!! !!}` with `{{ }}`.
     - Task 4: ViewComposer gatekeepers `GridComposer` and `LayoutComposer` under `BlatUI\Admin\View\Composers`, registered in `AdminServiceProvider`, sanitizing scalar variables.
     - Task 5: Document architectural patterns in `AGENTS.md`.
   - `.agents/teamwork/ORIGINAL_REQUEST.md`:
     - Full acceptance criteria: 0 Pest failures, 0 PHPStan errors, 0 Pint violations, 100% type coverage, strict contracts.

2. **Existing Grid Components (`src/Grid/`)**:
   - `src/Grid/Filter.php`:
     - Line 24: Already declared as `class Filter implements Htmlable, Renderable, Stringable`.
     - Line 480: Implements `public function toHtml(): string { return $this->render(); }`.
     - Line 390: Inside `Filter::render()`, an anonymous proxy class `$filterProxy` wraps `$this` when rendering `$this->view`.
   - `src/Grid/Tools.php`:
     - Line 13: Already declared as `class Tools implements Htmlable, Renderable, Stringable`.
     - Line 551: Implements `public function toHtml(): string { return $this->render(); }`.
   - `src/Grid.php`:
     - Lines 553-597: In `Grid::render()`, anonymous proxy classes `$toolsProxy` and `$filterProxy` are created to wrap `$this->tools` and `$this->filter`. Currently, `$toolsProxy` and `$filterProxy` only declare `render()` and `__toString()`, but **do not implement `Illuminate\Contracts\Support\Htmlable`** or `toHtml()`.
   - `src/Grid/Column.php`:
     - Line 15: Currently declared as `class Column` (does NOT implement `Htmlable`).
     - Lines 218-305: Built-in displayers (`badge`, `link`, `copyable`, `image`, `datetime`, `using`, `limit`) return `\Illuminate\Support\HtmlString` with internal values escaped via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
     - Lines 345-368: `renderCell()` passes `Htmlable` instances through untouched, while escaping plain scalar/stringable values with `htmlspecialchars`.
   - `src/Grid/Row.php`:
     - Line 21: Declared as `class Row implements Arrayable` (does NOT implement `Htmlable`).
     - Lines 525-530: `public function cell(Column $column): string` returns a primitive string from `$column->renderCell()`.
   - `src/Grid/Filter/Field.php`:
     - Line 15: Declared as `abstract class Field implements Htmlable, Renderable, Stringable`.
     - Line 335: Implements `toHtml(): string` returning `$this->render()`.

3. **Blade Views (`resources/views/`)**:
   - `resources/views/grid/table.blade.php`:
     - Line 32: `{!! $filter->render() !!}`.
     - Line 37: `{!! $tools->render() !!}`.
     - Line 106: `{!! $row->renderCheckbox() !!}`.
     - Line 112: `{!! $row->cell($column) !!}`.
     - Line 118: `{!! $row->renderActions() !!}`.
   - `resources/views/grid/filter.blade.php`:
     - Line 47: `{!! $field->render() !!}` inside the fields loop.
   - `resources/views/layouts/app.blade.php`:
     - Lines 86-88: `{!! is_string($content) ? $content : $content->renderRows() !!}`.

4. **Service Provider (`src/AdminServiceProvider.php`)**:
   - Lines 37-49: `boot()` registers routes, views (`blatui-admin`), translations, and migrations. No view composers are currently registered.

5. **Test Suite Baseline**:
   - Running `vendor/bin/pest`: 107 tests total, 106 passing, 1 failing.
   - Failing test in `tests/Unit/GridColumnTest.php:465`:
     `P\Tests\Unit\GridColumnTest::__pest_evaluable_grid_column_implements_Htmlable_contract_for_rendering_scope`
     `Failed asserting that an instance of class BlatUI\Admin\Grid\Column is an instance of interface Illuminate\Contracts\Support\Htmlable.`
   - `vendor/bin/phpstan analyse`: Passed with 0 errors.
   - `composer test:types`: 100.0% type coverage across all 53 files in `src/`.
   - `vendor/bin/pint --test`: Failed only on `tests/Unit/GridColumnTest.php` due to formatting of the newly added test.

---

## 2. Logic Chain

1. **Grid Component Contracts (Task 2 / R1)**:
   - `Column` must implement `\Illuminate\Contracts\Support\Htmlable`. In rendering context (table header), `$column->toHtml()` must return `(string) $this->getLabel()`.
   - `Row` must implement `\Illuminate\Contracts\Support\Htmlable`. In rendering scope, `$row->toHtml()` returns `(string) ($this->getKey() ?? '')`.
   - `Row::cell(Column $column)` must return `\Illuminate\Support\HtmlString` rather than `string`. Wrapping `$column->renderCell($value, $this->data)` in `new HtmlString(...)` explicitly communicates to Laravel Blade that cell contents are safe.
   - Displayer logic preserves safety: Built-in displayers already escape inner dynamic values (like text, href, labels) while maintaining valid HTML markup (e.g. badges, anchor tags). Because `Column::renderCell()` passes `Htmlable` through directly and escapes non-Htmlable values, wrapping the cell return in `HtmlString` allows Blade's `{{ $row->cell($column) }}` to render rich components without double-escaping, while preventing any unescaped XSS payload from bypassing encoding.
   - **Crucial Discovery regarding Grid proxies**: In `src/Grid.php`, `$tools` and `$filter` are passed to `table.blade.php` as `$toolsProxy` and `$filterProxy`. Even if `Filter` and `Tools` implement `Htmlable`, if `$toolsProxy` and `$filterProxy` do not implement `Htmlable`, Blade's `e($tools)` will call `htmlspecialchars((string) $tools)` and corrupt the entire toolbar HTML into escaped text (`&lt;div...&gt;`). Thus, `$toolsProxy` and `$filterProxy` in `Grid.php` (and in `Filter.php`) MUST implement `\Illuminate\Contracts\Support\Htmlable` and provide `toHtml(): string`.

2. **Blade View Modernization (Task 3 / R2)**:
   - In `resources/views/grid/table.blade.php`:
     - `{!! $tools->render() !!}` -> `{{ $tools }}`
     - `{!! $filter->render() !!}` -> `{{ $filter }}`
     - `{!! $row->cell($column) !!}` -> `{{ $row->cell($column) }}`
   - In `resources/views/grid/filter.blade.php`:
     - `{!! $field->render() !!}` -> `{{ $field }}`
   - In `resources/views/layouts/app.blade.php`:
     - `{!! is_string($content) ? $content : $content->renderRows() !!}` -> `{{ $content }}`
     - In `Content.php`, `$data['content']` should be passed as `new HtmlString($this->renderRows())` so that `{{ $content }}` invokes `toHtml()` and renders the layout rows cleanly.

3. **ViewComposer Defensive Gatekeepers (Task 4 / R3)**:
   - Location: `src/View/Composers/GridComposer.php` and `src/View/Composers/LayoutComposer.php` under namespace `BlatUI\Admin\View\Composers`.
   - Registration: In `AdminServiceProvider::boot()`, call `View::composer('blatui-admin::grid.*', GridComposer::class)` and `View::composer('blatui-admin::layouts.*', LayoutComposer::class)`.
   - **Sanitization Mechanism & Anti-Double-Escaping Rule**:
     - If a composer directly runs `htmlspecialchars($val)` on a string variable, and Blade later renders it with `{{ $val }}`, Laravel's `e()` function executes a second pass of `htmlspecialchars`, resulting in double-escaped entities like `&amp;lt;script&amp;gt;`.
     - To ensure complete security without double-escaping, the composer must sanitize raw scalar strings with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` and wrap them in `new \Illuminate\Support\HtmlString(...)`.
     - When Blade processes `{{ $title }}`, `e()` recognizes `Htmlable` and outputs `&lt;script&gt;` once. If a legacy view still uses `{!! $title !!}`, it also outputs `&lt;script&gt;`. Malicious input is neutralized in all scenarios.
     - Exemption for `$content`: If `$content` is already an `Htmlable` instance (e.g. from `Content::render()`), `LayoutComposer` must preserve it without stringification. If an unvetted scalar string is injected as `$content`, `LayoutComposer` sanitizes it.

4. **Documentation Updates (Task 5 / R4)**:
   - `AGENTS.md` must document the ViewModel separation pattern, the mandatory `Htmlable` contract on all UI components, the rule prohibiting PHP string concatenation for HTML, and the ViewComposer gatekeeper mechanism.

---

## 3. Features Discovered

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Contract | `Column::toHtml()` | Enables `Column` to implement `Illuminate\Contracts\Support\Htmlable` for table headers | None | `string` (column label) | Returns empty string if label null/empty | `tests/Unit/GridColumnTest.php:465` |
| 2 | Contract | `Row::toHtml()` | Enables `Row` to implement `Illuminate\Contracts\Support\Htmlable` | None | `string` (resolved primary key or row number) | Fallback to empty string if no key | `docs/superpowers/plans/2026-09-27-rendering-architecture-refactor.md` Task 2 |
| 3 | Contract | `Row::cell()` return type | Refactored return type from `string` to `\Illuminate\Support\HtmlString` | `Column $column` | `\Illuminate\Support\HtmlString` | Throws `TypeError` if non-Column passed | `ORIGINAL_REQUEST.md` R1 |
| 4 | Security | Displayer Safe HTML Pass-through | Built-in displayers return `HtmlString` with sanitized values and structured markup | Variant, map, limit, format | `\Illuminate\Support\HtmlString` | Encodes user inputs via `htmlspecialchars`, prevents double-encoding | `src/Grid/Column.php:218-305` |
| 5 | Proxy Contract | `Grid::$toolsProxy` Htmlable | `$toolsProxy` in `Grid::render()` must implement `Htmlable` | None | `string` (rendered toolbar) | If missing, Blade `{{ $tools }}` encodes HTML tags into entities | Code inspection of `src/Grid.php:553` |
| 6 | Proxy Contract | `Grid::$filterProxy` Htmlable | `$filterProxy` in `Grid::render()` must implement `Htmlable` | None | `string` (rendered filter form) | If missing, Blade `{{ $filter }}` encodes HTML tags into entities | Code inspection of `src/Grid.php:576` |
| 7 | Proxy Contract | `Filter::$filterProxy` Htmlable | Proxy inside `Filter::render()` for custom views | None | `string` | Missing `Htmlable` causes escaping in views | Code inspection of `src/Grid/Filter.php:390` |
| 8 | View Layer | `table.blade.php` Modernization | Migrate raw `{!! !!}` to native Blade `{{ }}` | `$tools`, `$filter`, `$row->cell($column)` | Safe HTML output | Blade `e()` automatically delegates to `toHtml()` | `docs/superpowers/plans/2026-09-27-rendering-architecture-refactor.md` Task 3 |
| 9 | View Layer | `filter.blade.php` Modernization | Migrate `{!! $field->render() !!}` to `{{ $field }}` | `Field $field` | Safe HTML output | `Field` already implements `Htmlable` | `resources/views/grid/filter.blade.php:47` |
| 10 | View Layer | `layouts/app.blade.php` Modernization | Migrate `{!! $content !!}` to `{{ $content }}` | `Htmlable $content` | Safe layout HTML | Plain strings get escaped; `HtmlString` passes through | `resources/views/layouts/app.blade.php:87` |
| 11 | Security Gatekeeper | `LayoutComposer` | Intercepts `blatui-admin::layouts.*` views and sanitizes scalar variables | `\Illuminate\View\View $view` | `void` (mutates view data) | Scalar strings sanitized to `HtmlString`; objects preserved | `src/View/Composers/LayoutComposer.php` |
| 12 | Security Gatekeeper | `GridComposer` | Intercepts `blatui-admin::grid.*` views, sanitizes scalar inputs, validates contracts | `\Illuminate\View\View $view` | `void` (mutates view data) | Sanitizes injected scalars; validates `tools`/`filter` instances | `src/View/Composers/GridComposer.php` |
| 13 | Service Provider | Composer Registration | Registers `GridComposer` and `LayoutComposer` in IoC container | Service container `boot()` | `void` | Registered before views are resolved | `src/AdminServiceProvider.php:37` |
| 14 | Architecture Docs | AGENTS.md Security Guidelines | Standard operating guidelines for ViewModels and rendering contracts | Documentation markdown | Markdown text | N/A | `AGENTS.md` |

---

## 4. Edge Cases

| # | Feature | Input | Observed Behavior |
|---|---------|-------|-------------------|
| 1 | `Row::cell()` with Badge Displayer | Value containing `<script>alert(1)</script>` | Badge HTML tags (`<span class="inline-flex...">`) are unescaped; inner text is encoded to `&lt;script&gt;alert(1)&lt;/script&gt;`. Wrapping in `HtmlString` prevents Blade `{{ }}` from double-escaping. |
| 2 | `Row::cell()` with Plain Column (No Displayer) | Raw value `'<img src=x onerror=alert(1)>'` | `Column::renderCell()` encodes string via `htmlspecialchars`. Wrapped in `HtmlString`, Blade `{{ }}` outputs `&lt;img src=x onerror=alert(1)&gt;`. No execution, no double escaping. |
| 3 | `table.blade.php` with `$toolsProxy` | `{{ $tools }}` evaluated in Blade | If `$toolsProxy` does NOT implement `Htmlable`, Blade casts it to string and escapes all toolbar HTML. When `$toolsProxy` implements `Htmlable`, Blade outputs the complete toolbar safely. |
| 4 | `LayoutComposer` with `$title` containing XSS | `$title = '<script>alert("hack")</script>'` | Sanitized to `new HtmlString('&lt;script&gt;alert(&quot;hack&quot;)&lt;/script&gt;')`. Blade `{{ $title }}` outputs single-escaped entities without double-escaping to `&amp;lt;`. |
| 5 | `LayoutComposer` with `$content` as `HtmlString` | `$content = new HtmlString('<div class="row">...</div>')` | `LayoutComposer` recognizes `Htmlable` and does NOT escape the layout markup. Blade `{{ $content }}` renders the layout structure cleanly. |
| 6 | `LayoutComposer` with `$content` as raw malicious string | `$content = '<script>malicious()</script>'` | Plain string is recognized as unvetted scalar and sanitized by `LayoutComposer` (or escaped by Blade `{{ $content }}`), neutralising script execution. |
| 7 | `Pest` test assertion on `Row::cell()` | `$row->cell($column)` checked with `toBe('administrator')` | Fails because `toBe()` performs strict identity comparison (`===`) between `HtmlString` and string. Tests must use `->toHtml()->toBe(...)` or `(string) $row->cell(...)`. |
| 8 | Empty or Null Values in `Column::renderCell()` | `null` or `''` with default fallback configured | Returns configured default value safely escaped inside `HtmlString`. |
| 9 | Filter resetUrl with Array Sort Parameters | `_sort[column]=id&_sort[type]=desc` | Properly preserved while stripping page and filter query keys; query string re-encoded safely. |

---

## 5. Caveats

1. **Proxy Objects in `Grid.php` and `Filter.php`**:
   The use of anonymous class proxies (`$toolsProxy`, `$filterProxy`) in `src/Grid.php` (lines 553-597) and `src/Grid/Filter.php` (line 390) is an internal implementation detail of BlatUI Admin. If any of these proxies omit `implements \Illuminate\Contracts\Support\Htmlable`, Blade's `{{ $tools }}` and `{{ $filter }}` will corrupt the rendered HTML. Both the underlying classes and their proxy wrappers must implement `Htmlable`.
2. **Pest Assertions on `HtmlString`**:
   Any existing unit tests that assert `$row->cell($column)->toBe('...')` must be updated to inspect the string representation (e.g. `(string) $row->cell($column)` or `$row->cell($column)->toHtml()`) or verify `toBeInstanceOf(HtmlString::class)`, because `HtmlString` is an object.
3. **Type Coverage Constraint**:
   The repository enforces `--type-coverage --min=100`. All newly introduced methods (e.g. `toHtml(): string`, `compose(View $view): void`) and properties must have explicit return and parameter type declarations.
4. **Pint Formatting**:
   All new files and edits must strictly conform to Laravel Pint (`vendor/bin/pint --test`).

---

## 6. Conclusion

- The rendering architecture refactor has a clear, actionable plan across Tasks 2 through 5.
- Task 1 is already completed and committed.
- Task 2 requires:
  - Adding `implements Htmlable` and `toHtml(): string` to `Column` and `Row`.
  - Updating `Grid.php` and `Filter.php` proxies to implement `Htmlable`.
  - Updating `Row::cell()` to return `\Illuminate\Support\HtmlString`.
- Task 3 requires:
  - Replacing `{!! !!}` with `{{ }}` in `table.blade.php`, `filter.blade.php`, and `layouts/app.blade.php`.
  - Ensuring `Content::render()` passes `$content` as an `HtmlString`.
- Task 4 requires:
  - Implementing `GridComposer` and `LayoutComposer` under `BlatUI\Admin\View\Composers`.
  - Registering them in `AdminServiceProvider::boot()`.
  - Sanitizing scalar variables with `new HtmlString(htmlspecialchars(..., ENT_QUOTES, 'UTF-8'))` to protect against XSS while preventing double-escaping.
  - Adding comprehensive security tests in `tests/Security/XssRenderingTest.php`.
- Task 5 requires:
  - Documenting these patterns under `## Rendering Architecture & Security` in `AGENTS.md`.

---

## 7. Verification Method

To independently verify the implementation once executed:

1. **Full Test Suite Verification**:
   ```bash
   vendor/bin/pest
   ```
   Must pass 100% of tests with 0 failures, including:
   - `tests/Security/XssRenderingTest.php`
   - `tests/Feature/GridFilterToolsTest.php`
   - `tests/Unit/GridColumnTest.php`
   - `tests/Unit/GridRowTest.php`

2. **Strict Type Coverage Verification**:
   ```bash
   composer test:types
   ```
   Must maintain 100.0% type coverage across all files in `src/`.

3. **Static Analysis Verification**:
   ```bash
   vendor/bin/phpstan analyse
   ```
   Must pass with 0 errors.

4. **Code Style Verification**:
   ```bash
   vendor/bin/pint --test
   ```
   Must pass with 0 formatting violations.

5. **Manual Invalidation Conditions**:
   - Any raw `<script>` tag appearing unescaped in rendered output when malicious input is supplied.
   - Any badge, link, or image markup rendered as raw entity characters (`&lt;span class=...`) in table cells.
   - Any double-encoded entity (`&amp;lt;`) in page titles or cell values.
