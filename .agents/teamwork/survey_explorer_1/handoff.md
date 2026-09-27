# Investigation Report: Grid Rendering Architecture & Security Hardening

## 1. Observation

### 1.1 Interface and Method State of Grid Core Classes

- **`src/Grid/Filter.php`**:
  - Class declaration (lines 24-25):
    ```php
    class Filter implements Htmlable, Renderable, Stringable
    ```
  - Interface methods already defined (lines 480-491):
    ```php
    public function toHtml(): string
    {
        return $this->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }
    ```
  - View Proxy (lines 389-416): When `render()` executes, it wraps `$this` into an anonymous proxy `$filterProxy` before passing it to `resources/views/grid/filter.blade.php`:
    ```php
    $filterProxy = new class($this) {
        public function __construct(protected Filter $filter) {}
        public function __call(string $method, array $args): mixed { return $this->filter->{$method}(...$args); }
        public function __get(string $name): mixed { return $this->filter->{$name}; }
    };
    ```
    This proxy does **not** implement `Htmlable`.

- **`src/Grid/Tools.php`**:
  - Class declaration (line 13):
    ```php
    class Tools implements Htmlable, Renderable, Stringable
    ```
  - Interface methods already defined (lines 551-562):
    ```php
    public function toHtml(): string
    {
        return $this->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }
    ```

- **`src/Grid/Column.php`**:
  - Class declaration (line 15):
    ```php
    class Column
    ```
    Currently implements **no** interfaces (`Htmlable` is not implemented).
  - Imports (lines 8, 12): `use Illuminate\Contracts\Support\Htmlable;` and `use Stringable;` are already present at the top of the file, but unused on the class declaration.
  - Cell rendering (lines 310-370):
    ```php
    public function renderCell(mixed $value, mixed $row = null): string
    ```
    - Iterates over `$this->callbacks`.
    - Lines 345-347:
      ```php
      // Htmlable/HtmlString instances are pre-escaped by displayers — pass through directly.
      if ($current instanceof Htmlable) {
          return $current->toHtml();
      }
      ```
    - Lines 350-367: For scalars, `Stringable`, arrays, and objects, it calls `htmlspecialchars((string) $current, ENT_QUOTES, 'UTF-8')`.

- **`src/Grid/Row.php`**:
  - Class declaration (line 21):
    ```php
    class Row implements Arrayable
    ```
    Implements only `Arrayable`. Neither `Htmlable` nor `Renderable` is implemented.
  - Cell method (lines 525-530):
    ```php
    public function cell(Column $column): string
    {
        $value = data_get($this->data, $column->getName());

        return $column->renderCell($value, $this->data);
    }
    ```
    Currently declared with `: string` return type, returning raw string from `$column->renderCell()`.
  - Rendering methods: Has `renderActions(): string` and `renderCheckbox(string $name = '_row_id'): string`. Currently has **no** `render(): string`, `toHtml(): string`, or `__toString(): string`.

### 1.2 Grid View Layer & Anonymous Proxies in `src/Grid.php`

- In `src/Grid.php` lines 553-608 (`render()` method):
  ```php
  $toolsProxy = new class($this->tools)
  {
      public function __construct(protected Tools $tools) {}
      public function render(): string { return $this->tools->render(); }
      public function __call(string $method, array $args): mixed { return $this->tools->{$method}(...$args); }
      public function __toString(): string { return $this->tools->render(); }
  };

  $filterProxy = new class($this->filter)
  {
      public function __construct(protected Filter $filter) {}
      public function render(): string { return $this->filter->render(); }
      public function __call(string $method, array $args): mixed { return $this->filter->{$method}(...$args); }
      public function __toString(): string { return $this->filter->render(); }
  };
  ```
  Neither `$toolsProxy` nor `$filterProxy` implements `\Illuminate\Contracts\Support\Htmlable`.
- In `resources/views/grid/table.blade.php`:
  - Line 32: `{!! $filter->render() !!}`
  - Line 37: `{!! $tools->render() !!}`
  - Line 112: `{!! $row->cell($column) !!}`

### 1.3 Column Displayers

All built-in displayers (`src/Grid/Displayers/`):
- `Badge.php` (line 87): returns formatted `<span class="...">...</span>` with classes and text encoded via `htmlspecialchars`.
- `Link.php` (line 49): returns `<a href="..." target="..." ...>...</a>` with href, target, and text encoded via `htmlspecialchars`.
- `Copyable.php` (line 34): returns Alpine markup with text and data-attribute encoded via `htmlspecialchars`.
- `Image.php` (line 48): returns `<img src="..." ...>` with src encoded via `htmlspecialchars`.
- `Datetime.php`, `Using.php`, `Limit.php`: return string values.
In `src/Grid/Column.php`:
- Lines 222-224 (`badge`): wraps result in `new HtmlString(...)`.
- Lines 234-236 (`link`): wraps result in `new HtmlString(...)`.
- Lines 246-248 (`copyable`): wraps result in `new HtmlString(...)`.
- Lines 258-260 (`image`): wraps result in `new HtmlString(...)`.
- Lines 270-274 (`datetime`): escapes via `htmlspecialchars()` and wraps in `new HtmlString(...)`.
- Lines 286-290 (`using`): escapes via `htmlspecialchars()` and wraps in `new HtmlString(...)`.
- Lines 300-304 (`limit`): escapes via `htmlspecialchars()` and wraps in `new HtmlString(...)`.

### 1.4 Test Suite State

- Executing `vendor/bin/pest tests/Unit/GridColumnTest.php`:
  - Fails with 1 error:
    `Failed asserting that an instance of class BlatUI\Admin\Grid\Column is an instance of interface Illuminate\Contracts\Support\Htmlable.`
    at line 465 (the test added in working tree).
- In `tests/Unit/GridRowTest.php`:
  - Line 63: `expect($row->cell($col1))->toBe('administrator');`
    Because `toBe()` performs strict equality (`assertSame`), changing `Row::cell()` to return `HtmlString` will fail this test unless updated to `$cell->toHtml()` or `(string) $cell`.
  - Line 67: `expect($row->cell($col2))->toContain('Active Status');`
- In `tests/Feature/GridFilterToolsTest.php`:
  - Line 235: Asserts `$filter->toHtml()`.
  - Has no assertion for `expect($filter)->toBeInstanceOf(Htmlable::class)` or `expect($tools)->toBeInstanceOf(Htmlable::class)`.
- In `tests/Security/XssRenderingTest.php`:
  - Only tests `Content` (Task 1). Does not yet test `Filter`, `Tools`, `Column`, `Row`, or `Row::cell()`.

---

## 2. Logic Chain

1. **Why `Column` fails contract compliance**:
   - `Column` is required to implement `Illuminate\Contracts\Support\Htmlable` (Requirement R1 and Task 2).
   - Currently, `Column` does not declare `implements Htmlable` and has no `toHtml()` method.
   - When instantiated, `expect($column)->toBeInstanceOf(Htmlable::class)` fails.
   - When a Column instance represents a header cell in rendering scopes, its textual HTML representation is its label (`$this->getLabel()`).
   - Therefore, implementing `Htmlable, Stringable` with `toHtml(): string { return $this->getLabel(); }` satisfies both the contract and `tests/Unit/GridColumnTest.php:466`.

2. **Why `Row` must implement `Htmlable` and refactor `cell()` to `HtmlString`**:
   - Requirement R1 and R2 mandate that `table.blade.php` uses `{{ $row->cell($column) }}` instead of `{!! $row->cell($column) !!}`.
   - In Laravel Blade, `{{ $expression }}` compiles to `echo e($expression);`.
   - The Laravel `e()` helper checks:
     ```php
     if ($value instanceof Htmlable) {
         return $value->toHtml();
     }
     return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
     ```
   - If `Row::cell()` returns a `string`, Blade's `e()` function runs `htmlspecialchars()` on the string.
   - If a column uses a displayer (such as `badge()`), the string returned is HTML markup (e.g., `<span class="...">Active</span>`).
   - If `cell()` returns a `string`, `e()` will turn `<span...>` into `&lt;span...&gt;`, corrupting the table UI with visible raw tags (double-escaping).
   - Furthermore, plain values in `Column::renderCell()` are *already* escaped via `htmlspecialchars()` on line 350. If `cell()` returns a `string`, Blade's `e()` would re-encode `&lt;script&gt;` into `&amp;lt;script&amp;gt;` (double entity encoding).
   - When `Row::cell()` returns `new HtmlString($column->renderCell($value, $this->data))`, Blade's `e()` recognizes the `Htmlable` contract and calls `toHtml()` without invoking `htmlspecialchars()`.
   - Displayer markup is preserved as DOM elements, while plain values and XSS payloads remain safely escaped exactly once.

3. **Why `Grid.php` proxies are a critical blocker for Task 3**:
   - In `resources/views/grid/table.blade.php`, `{!! $tools->render() !!}` and `{!! $filter->render() !!}` must be replaced with `{{ $tools }}` and `{{ $filter }}`.
   - Although `Tools` and `Filter` classes implement `Htmlable`, `Grid::render()` passes `$toolsProxy` and `$filterProxy` instead of `$this->tools` and `$this->filter`.
   - Because `$toolsProxy` and `$filterProxy` do **not** declare `implements Htmlable`, Blade `{{ $tools }}` and `{{ $filter }}` will execute their `__toString()` and then run `htmlspecialchars()` over the entire toolbar and filter card markup.
   - This would completely break the HTML rendering of both the Filter and the Tools toolbar when switching to `{{ }}` in Task 3.
   - Therefore, either the anonymous proxies in `Grid::render()` must implement `Htmlable`, or `Grid::render()` should pass `$this->tools` and `$this->filter` directly.

4. **Why `tests/Unit/GridRowTest.php` requires update**:
   - Line 63 asserts: `expect($row->cell($col1))->toBe('administrator')`.
   - When `Row::cell()` returns an `HtmlString`, strict equality `assertSame('administrator', $cell)` fails because string != object.
   - Line 63 must be adjusted to `expect($row->cell($col1))->toBeInstanceOf(HtmlString::class)->and($row->cell($col1)->toHtml())->toBe('administrator')`.

---

## 3. Caveats

1. **Proxy Objects in `src/Grid.php` and `src/Grid/Filter.php`**:
   - The plan mentions `src/Grid/Filter.php` and `src/Grid/Tools.php`, but did not explicitly note that `src/Grid.php` wraps both in anonymous class proxies inside `Grid::render()`. Downstream implementers must address this in Task 2/Task 3 to avoid rendering failures when Blade is migrated to `{{ }}`.
2. **Read-only Investigation**:
   - In accordance with explorer constraints, no package files or tests were modified during this investigation.
3. **Pint Code Style**:
   - The uncommitted test in `tests/Unit/GridColumnTest.php` currently triggers a Pint formatting violation (`vendor/bin/pint --test` reports 1 file). When modifying tests in Task 2, `vendor/bin/pint` should be run to ensure compliance.

---

## 4. Conclusion

The exact code changes required across the Grid components and test suite are:

### A. `src/Grid/Column.php`
1. Add `implements Htmlable, Stringable` to `class Column`.
2. Add `toHtml(): string` returning `$this->getLabel()`.
3. Add `__toString(): string` returning `$this->toHtml()`.

### B. `src/Grid/Row.php`
1. Add `use Illuminate\Contracts\Support\Htmlable;`, `use Illuminate\Contracts\Support\Renderable;`, `use Illuminate\Support\HtmlString;`, `use Stringable;`.
2. Add `implements Arrayable, Htmlable, Renderable, Stringable` to `class Row`.
3. Refactor `cell(Column $column): HtmlString`:
   ```php
   public function cell(Column $column): HtmlString
   {
       $value = data_get($this->data, $column->getName());

       return new HtmlString($column->renderCell($value, $this->data));
   }
   ```
4. Add `render(): string { return $this->renderActions(); }`.
5. Add `toHtml(): string { return $this->render(); }`.
6. Add `__toString(): string { return $this->toHtml(); }`.

### C. `src/Grid.php`
Ensure `$toolsProxy` and `$filterProxy` in `Grid::render()` implement `\Illuminate\Contracts\Support\Htmlable` (or pass `$this->tools` and `$this->filter` directly):
```php
$toolsProxy = new class($this->tools) implements Htmlable { ... public function toHtml(): string { return $this->tools->render(); } };
$filterProxy = new class($this->filter) implements Htmlable { ... public function toHtml(): string { return $this->filter->render(); } };
```

### D. Test Updates
1. **`tests/Unit/GridColumnTest.php`**:
   Format line 462-467 with proper imports so Pint passes.
2. **`tests/Unit/GridRowTest.php`**:
   Update line 63 to assert `HtmlString` instance and `toHtml()` string equality.
   Add test verifying `Row` implements `Htmlable` and `toHtml()`.
3. **`tests/Feature/GridFilterToolsTest.php`**:
   Add assertions that `Filter` and `Tools` implement `Htmlable`.
4. **`tests/Security/XssRenderingTest.php`**:
   Add dedicated security test assertions for:
   - `Row::cell()` wrapping XSS payload in `HtmlString` with entities properly escaped (`&lt;script&gt;`).
   - `Row::cell()` with `badge()` preserving `<span class="...">` while escaping content.
   - Blade `{{ $row->cell($column) }}` rendering without double-escaping.

---

## 5. Verification Method

To independently verify these findings:

1. **Verify Column Htmlable Failure**:
   ```bash
   vendor/bin/pest tests/Unit/GridColumnTest.php --filter="grid column implements Htmlable"
   ```
   *Expected*: Fails asserting `Column` is instance of `Htmlable`.

2. **Verify Row Cell Return Type**:
   Inspect line 525 of `src/Grid/Row.php`:
   ```bash
   sed -n '524,531p' src/Grid/Row.php
   ```
   *Expected*: Shows `public function cell(Column $column): string`.

3. **Verify Grid.php Proxies Lack Htmlable**:
   Inspect lines 553-598 of `src/Grid.php`:
   ```bash
   sed -n '553,598p' src/Grid.php
   ```
   *Expected*: Shows anonymous classes without `implements Htmlable`.

4. **Verify Full Test Suite & Static Analysis Baseline**:
   ```bash
   vendor/bin/pest
   vendor/bin/phpstan analyse
   vendor/bin/pint --test
   ```
