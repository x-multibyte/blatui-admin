# Grid Filter Field Blade Templates — Implementation Specification

> **Status: COMPLETE (verified 2026-10-04)**  
> **Authority:** This document defines the implementation blueprint for migrating Grid Filter fields from PHP heredocs to Blade templates, completing Item 1 of the open tasks in `docs/superpowers/plans/2026-09-27-rendering-architecture-refactor.md` in strict adherence to `AGENTS.md`.

---

## 1. Objective

Migrate the Grid filter field classes (`Field`, `Between`, `In`) from inline PHP heredoc HTML string generation and string concatenation to dedicated Blade templates in `resources/views/grid/filter/`. This refactor fulfills the core ViewModel / View separation mandated by `AGENTS.md`, eliminates all heredocs from `src/Grid/Filter/`, leverages Blade compile-time escaping (`{{ }}`) as the sole security boundary to prevent double escaping, and introduces an anonymous proxy during rendering to make recursive view evaluation loops physically impossible.

---

## 2. Current State (Evidenced)

### 2.1 Heredoc String Building in `src/Grid/Filter/`
A grep across `src/Grid/Filter/` confirms that three files contain raw heredoc HTML string generation:

```bash
$ grep -rn "<<<" src/Grid/Filter/
src/Grid/Filter/Between.php:107:        return <<<HTML
src/Grid/Filter/Field.php:313:        return <<<HTML
src/Grid/Filter/In.php:115:        return <<<HTML
```

1. **`src/Grid/Filter/Field.php:304-330`**:
   `Field::render()` manually escapes scalars with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` and interpolates them into an inline heredoc string for text-like inputs:
   ```php
   public function render(): string
   {
       $id = htmlspecialchars($this->getId(), ENT_QUOTES, 'UTF-8');
       $name = htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8');
       $label = htmlspecialchars($this->getLabel(), ENT_QUOTES, 'UTF-8');
       $rawVal = $this->getValue();
       $value = is_scalar($rawVal) ? htmlspecialchars((string) $rawVal, ENT_QUOTES, 'UTF-8') : '';
       $placeholder = htmlspecialchars($this->getPlaceholder() ?? $this->getLabel(), ENT_QUOTES, 'UTF-8');

       return <<<HTML
   <div class="flex flex-col gap-1.5">
       <label for="{$id}" class="text-xs font-medium text-gray-700 dark:text-gray-300">
           {$label}
       </label>
       <div class="relative">
           <input
               id="{$id}"
               type="text"
               name="{$name}"
               value="{$value}"
               placeholder="{$placeholder}"
               class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
           />
       </div>
   </div>
   HTML;
   }
   ```

2. **`src/Grid/Filter/Between.php:98-133`**:
   `Between::render()` overrides `render()` to manually format and concatenate two text inputs (`_start` and `_end`) into an inline heredoc.

3. **`src/Grid/Filter/In.php:98-131`**:
   `In::render()` overrides `render()` to concatenate an `$optionsHtml` string in PHP and interpolate it into an inline heredoc `<select>` container. If `$this->options` is empty, it delegates to `parent::render()`.

4. **`src/Grid/Filter.php:423-480`**:
   `Filter::render()` contains dead legacy fallback heredoc code (heredoc on line 447) from before `filter.blade.php` was introduced. When `blatui-admin::grid.filter` exists, lines 389-421 execute and delegate rendering to `resources/views/grid/filter.blade.php`.

### 2.2 Subclasses of `Field`
Inspection confirms that all other filter fields inherit `render()` directly from `Field` without overrides:
- `src/Grid/Filter/Equal.php` (extends `Field`, implements `apply()`)
- `src/Grid/Filter/Gt.php` (extends `Field`, implements `apply()`)
- `src/Grid/Filter/Gte.php` (extends `Field`, implements `apply()`)
- `src/Grid/Filter/Lt.php` (extends `Field`, implements `apply()`)
- `src/Grid/Filter/Lte.php` (extends `Field`, implements `apply()`)
- `src/Grid/Filter/Like.php` (extends `Field`, implements `apply()`)
- `src/Grid/Filter/Where.php` (extends `Field`, implements `apply()`)

None of these subclasses contain HTML rendering code.

### 2.3 Existing Characterisation Tests
`tests/Unit/GridFilterFieldRenderTest.php` contains 17 tests (49 assertions) that pin down:
- Form parameter naming conventions (`username`, `created_at[start]`, `created_at[end]`, `id`).
- Element ID generation (`$field->getId()`).
- HTML attribute presence and nesting order via the `fingerprint()` function:
  - `equal`: `div[class] label[class,for] div[class] input[class,id,name,placeholder,type,value]`
  - `between`: `div[class] label[class] div[class] input[class,id,name,placeholder,type,value] span[class] input[class,id,name,placeholder,type,value]`
  - `in-with-options`: `div[class] label[class,for] div[class] select[class,id,name] option[value] option[value] option[value]`
  - `in-without-options-falls-back-to-text`: `div[class] label[class,for] div[class] input[class,id,name,placeholder,type,value]`
- XSS neutralization and handling of non-scalar inputs.

---

## 3. Architecture & Security Mechanics

### 3.1 Resolving the Recursive View Evaluation Risk
In `resources/views/grid/filter.blade.php`, registered fields are looped and rendered:
```blade
@foreach ($fields as $field)
    {{ $field }}
@endforeach
```
Because `Field` implements `\Illuminate\Contracts\Support\Htmlable`, Blade evaluates `{{ $field }}` by invoking `$field->toHtml()`, which calls `$field->render()`.

If `Field::render()` were to pass `$this` directly as `'field' => $this` into `text.blade.php`, and a developer or partial inside the template evaluated `{{ $field }}`, Blade would re-invoke `toHtml()`, creating an infinite recursive loop that exhausts PHP memory and blows the call stack.

**Solution: Anonymous Non-Htmlable Proxy**
`Field::render()` generates an anonymous proxy instance wrapping `$this`:
```php
protected function newProxy(): object
{
    return new class($this)
    {
        public function __construct(protected Field $field) {}

        /**
         * @param  array<int, mixed>  $args
         */
        public function __call(string $method, array $args): mixed
        {
            return $this->field->{$method}(...$args);
        }

        public function __get(string $name): mixed
        {
            $getter = 'get'.ucfirst($name);
            if (method_exists($this->field, $getter)) {
                return $this->field->{$getter}();
            }

            throw new \InvalidArgumentException("Undefined property or getter for '{$name}' on Field proxy.");
        }
    };
}
```
Key invariants of `$fieldProxy`:
1. It proxies all method calls and getter accesses (e.g. `$field->getId()`, `$field->getName()`).
2. **Scope Protection & Getter Requirement**: In PHP, an anonymous class is NOT a subclass of `Field`, and `Field` defines no `__get()`. Direct property access to `protected` properties like `$field->column` or `$field->name` is illegal. The proxy's `__get()` resolves getters (`getId()`), and templates must strictly use explicit getter methods rather than raw dynamic property access.
3. It does **NOT** implement `\Illuminate\Contracts\Support\Htmlable`, `\Illuminate\Contracts\Support\Renderable`, or `\Stringable`.
4. If `{{ $field }}` is accidentally called inside the Blade view, PHP immediately throws a `ViewException` (`Object of class class@anonymous could not be converted to string`) on the exact line rather than entering an infinite recursion loop.
5. **Known Legacy Issue Recorded**: `Filter.php:389` contains an older anonymous Htmlable proxy with the same protected property limitation, which causes `isset($filter)` in `resources/views/grid/filter.blade.php` to evaluate to `false` (currently harmless because `$fields` is explicitly passed). Documented here to prevent regressions during future maintenance.

### 3.2 Eliminating Double Escaping
Under `AGENTS.md`:
> "Blade Escaping as the Sole Security Boundary: All dynamic data reaches the view through native Blade `{{ }}`, which applies `e()` HTML-entity encoding. No additional sanitization layer runs before the Blade engine."

If PHP pre-escapes strings via `htmlspecialchars()` before passing them to Blade, Blade's `{{ $value }}` applies `e()` (which double-encodes by default), turning `Tom & Jerry` into `Tom &amp;amp; Jerry` and breaking attribute output.

**Resolution:**
1. The PHP ViewModel converts non-scalar input to empty string `''` and passes raw scalar strings to the view.
2. Blade templates use standard `{{ $id }}`, `{{ $name }}`, `{{ $label }}`, `{{ $value }}`, `{{ $placeholder }}`.
3. For `<option>` selection in `select.blade.php`:
   Blade's `@selected(...)` directive outputs `'selected'`. To ensure exact character-level match with existing characterisation tests and prevent any whitespace discrepancies before `>`, explicit inline ternary attribute rendering is used:
   ```blade
   <option value="{{ $key }}"{{ in_array((string) $key, $selectedValues, true) ? ' selected' : '' }}>{{ $optLabel }}</option>
   ```
   This guarantees:
   - `$key` is escaped by `{{ $key }}`
   - `$optLabel` is escaped by `{{ $optLabel }}`
   - When selected is true: `<option value="2" selected>Editor</option>`
   - When selected is false: `<option value="1">Admin</option>` (zero whitespace before `>`)
   - Exact DOM fingerprint match with zero double-escaping.

---

## 4. Target State & Implementation Details

### 4.1 New Blade Templates

> **@props 模式说明**：沿用 package 既有 `resources/views/grid/filter.blade.php` 的 `@props` 模式。在非组件环境使用 `view()->render()` 渲染时，Blade 的 ViewFactory 会将传入的关联数组解包绑定到 `@props`，并自动提供空的 `$attributes` 集合。
> 
> 由于 PHP 侧 ViewModel 的 `defaultVariables()` 始终显式传入全部同名键，模板无需编写冗余的 `$id = $id ?? ...` 回退逻辑，直接使用解构变量即可。

#### A. `resources/views/grid/filter/text.blade.php`
```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
])

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <div class="relative">
        <input
            id="{{ $id }}"
            type="text"
            name="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
    </div>
</div>
```

#### B. `resources/views/grid/filter/between.blade.php`
```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'startVal' => '',
    'endVal' => '',
])

<div class="flex flex-col gap-1.5">
    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <div class="flex items-center gap-1.5">
        <input
            id="{{ $id }}_start"
            type="text"
            name="{{ $name }}[start]"
            value="{{ $startVal }}"
            placeholder="From"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
        <span class="text-xs text-gray-400 shrink-0">—</span>
        <input
            id="{{ $id }}_end"
            type="text"
            name="{{ $name }}[end]"
            value="{{ $endVal }}"
            placeholder="To"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
    </div>
</div>
```

#### C. `resources/views/grid/filter/select.blade.php`
```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'options' => [],
    'selectedValues' => [],
])

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        >
            <option value="">All</option>
            @foreach ($options as $key => $optLabel)
                <option value="{{ $key }}"{{ in_array((string) $key, $selectedValues, true) ? ' selected' : '' }}>{{ $optLabel }}</option>
            @endforeach
        </select>
    </div>
</div>
```

---

### 4.2 PHP Class Modifications

#### A. `src/Grid/Filter/Field.php`
1. Add `$view` property and fluent `view()` accessor/mutator:
   ```php
   /**
    * View template name.
    */
   protected string $view = 'blatui-admin::grid.filter.text';

   /**
    * Set or get custom view template.
    */
   public function view(?string $view = null): static|string
   {
       if ($view === null) {
           return $this->view;
       }

       $this->view = $view;

       return $this;
   }

   /**
    * Get view template name.
    */
   public function getView(): string
   {
       return $this->view;
   }
   ```
2. Add `newProxy()`:
   ```php
   /**
    * Create a safe proxy wrapper to prevent recursive view evaluation.
    */
   protected function newProxy(): object
   {
       return new class($this)
       {
           public function __construct(protected Field $field) {}

           /**
            * @param  array<int, mixed>  $args
            */
           public function __call(string $method, array $args): mixed
           {
               return $this->field->{$method}(...$args);
           }

           public function __get(string $name): mixed
           {
               $getter = 'get'.ucfirst($name);
               if (method_exists($this->field, $getter)) {
                   return $this->field->{$getter}();
               }

               throw new \InvalidArgumentException("Undefined property or getter for '{$name}' on Field proxy.");
           }
       };
   }
   ```
3. Add `defaultVariables()`:
   ```php
   /**
    * Get default variables for the Blade view.
    *
    * @return array<string, mixed>
    */
   protected function defaultVariables(): array
   {
       $rawVal = $this->getValue();

       return [
           'field' => $this->newProxy(),
           'id' => $this->getId(),
           'name' => $this->getName(),
           'label' => $this->getLabel(),
           'value' => is_scalar($rawVal) ? (string) $rawVal : '',
           'placeholder' => $this->getPlaceholder() ?? $this->getLabel(),
       ];
   }
   ```
4. Update `render()`:
   ```php
   /**
    * Render the field input HTML.
   */
   public function render(): string
   {
       if (function_exists('view') && view()->exists($this->view)) {
           return view($this->view, $this->defaultVariables())->render();
       }

       return '';
   }
   ```

#### B. `src/Grid/Filter/Between.php`
1. Set default view:
   ```php
   /**
    * View template name.
    */
   protected string $view = 'blatui-admin::grid.filter.between';
   ```
2. Implement `defaultVariables()`:
   ```php
   /**
    * Get default variables for the Blade view.
    *
    * @return array<string, mixed>
    */
   protected function defaultVariables(): array
   {
       $range = $this->getRangeValue();

       return [
           'field' => $this->newProxy(),
           'id' => $this->getId(),
           'name' => $this->getName(),
           'label' => $this->getLabel(),
           'startVal' => is_scalar($range['start']) ? (string) $range['start'] : '',
           'endVal' => is_scalar($range['end']) ? (string) $range['end'] : '',
       ];
   }
   ```
3. Update `render()`:
   ```php
   /**
    * Render the between input fields.
    */
   public function render(): string
   {
       if (function_exists('view') && view()->exists($this->view)) {
           return view($this->view, $this->defaultVariables())->render();
       }

       return '';
   }
   ```

#### C. `src/Grid/Filter/In.php`
1. Set default view and fallback view:
   ```php
   /**
    * View template name.
    */
   protected string $view = 'blatui-admin::grid.filter.select';

   /**
    * Fallback view template name when options are empty.
    */
   protected string $fallbackView = 'blatui-admin::grid.filter.text';

   /**
    * Set or get custom fallback view template.
    */
   public function fallbackView(?string $view = null): static|string
   {
       if ($view === null) {
           return $this->fallbackView;
       }

       $this->fallbackView = $view;

       return $this;
   }

   /**
    * Get fallback view template name.
    */
   public function getFallbackView(): string
   {
       return $this->fallbackView;
   }
   ```
2. Implement `defaultVariables()`:
   ```php
   /**
    * Get default variables for the Blade view.
    *
    * @return array<string, mixed>
    */
   protected function defaultVariables(): array
   {
       return [
           'field' => $this->newProxy(),
           'id' => $this->getId(),
           'name' => $this->getName(),
           'label' => $this->getLabel(),
           'options' => $this->options,
           'selectedValues' => array_map('strval', $this->getValues()),
       ];
   }
   ```
3. Update `render()`:
   ```php
   /**
    * Render the in filter field.
    */
   public function render(): string
   {
       $view = empty($this->options) ? $this->getFallbackView() : $this->view;

       if (function_exists('view') && view()->exists($view)) {
           $vars = empty($this->options) ? parent::defaultVariables() : $this->defaultVariables();

           return view($view, $vars)->render();
       }

       return '';
   }
   ```

#### D. `src/Grid/Filter.php` (Legacy Heredoc Clean-up)
In `Filter::render()`, replace dead fallback lines 423-480 (where the legacy heredoc resides on line 447) with `return '';` (matching `Grid.php:620`), completely removing the legacy heredoc.

---

## 5. Behavioural Impact

### Preserved
- **DOM Structure & Fingerprints**: Every rendered element tag, attribute list, and nesting order is character-identical to current output (`equal`, `between`, `in-with-options`, `in-without-options-falls-back-to-text`).
- **CSS Classes**: All Tailwind CSS classes (`flex flex-col gap-1.5`, `px-2.5 py-1`, etc.) remain identical.
- **GET Form Contracts**: Parameter names `name="username"`, `name="created_at[start]"`, `name="created_at[end]"`, `name="id"` remain unchanged.
- **XSS Sanitization**: Attribute values with XSS payloads are safely entity-encoded by Blade `{{ }}`.
- **Non-scalar Fallback**: Arrays or non-scalar values fall back to `value=""` without `TypeError`.
- **Contracts**: `Field` continues to implement `\Illuminate\Contracts\Support\Htmlable`, `\Illuminate\Contracts\Support\Renderable`, and `\Stringable`.

### Known Out-of-Scope (本次不处理)
- **Custom HTML Attributes**: `Field::attributes()` collected custom attributes are not outputted to the DOM in current code before this refactor, and are not outputted in this phase either. Behavior is strictly preserved (本次不处理，后续统一规划 HTML 属性渲染机制)。

### Changed
- **Markup Location**: Moved out of PHP heredocs into Blade partials under `resources/views/grid/filter/`.
- **Customizability**: Filter fields now expose fluent `view(?string $view = null)` and `getView(): string` methods allowing custom Blade templates.

---

## 6. Test Plan

### 6.1 Tests to Invert
**None.** All 17 existing tests (49 assertions) in `tests/Unit/GridFilterFieldRenderTest.php` assert correct characterisation behaviour. They must pass without modification.

### 6.2 Tests to Add
Add the following tests in `tests/Unit/GridFilterFieldRenderTest.php`:

1. `filter fields use configured default blade views`:
   ```php
   test('filter fields use configured default blade views', function () {
       $equal = new Equal('title', 'Title');
       $between = new Between('created_at', 'Created At');
       $in = new In('status', 'Status');

       expect($equal->getView())->toBe('blatui-admin::grid.filter.text')
           ->and($between->getView())->toBe('blatui-admin::grid.filter.between')
           ->and($in->getView())->toBe('blatui-admin::grid.filter.select')
           ->and($in->getFallbackView())->toBe('blatui-admin::grid.filter.text');
   });
   ```

2. `filter fields allow customizing the blade view and fallback view`:
   ```php
   test('filter fields allow customizing the blade view and fallback view', function () {
       $field = new Equal('title', 'Title');
       $field->view('custom.filter.text');
       expect($field->getView())->toBe('custom.filter.text');

       $in = new In('status', 'Status');
       $in->view('custom.filter.select')->fallbackView('custom.filter.fallback');
       expect($in->getView())->toBe('custom.filter.select')
           ->and($in->getFallbackView())->toBe('custom.filter.fallback');
   });
   ```

3. `field proxy prevents recursive view evaluation loop`:
   ```php
   test('field proxy inside blade prevents recursive evaluation loop', function () {
       $field = new Equal('title', 'Title');

       // A blade snippet attempting to cast $field directly to string
       expect(fn () => \Illuminate\Support\Facades\Blade::render('{{ $field }}', [
           'field' => (new ReflectionMethod($field, 'newProxy'))->invoke($field),
       ]))->toThrow(\Throwable::class);
   });
   ```

4. `field implements Htmlable, Renderable, and Stringable and renders view`:
   ```php
   test('field implements Htmlable, Renderable, and Stringable returning identical output', function () {
       $field = new Equal('title', 'Title');

       expect($field)->toBeInstanceOf(\Illuminate\Contracts\Support\Htmlable::class)
           ->and($field)->toBeInstanceOf(\Illuminate\Contracts\Support\Renderable::class)
           ->and($field)->toBeInstanceOf(\Stringable::class)
           ->and($field->toHtml())->toBe($field->render())
           ->and((string) $field)->toBe($field->render());
   });
   ```

5. `filter fields source code contains zero heredocs`:
   ```php
   test('filter fields source code contains zero heredocs', function () {
       $files = [
           __DIR__.'/../../src/Grid/Filter/Field.php',
           __DIR__.'/../../src/Grid/Filter/Between.php',
           __DIR__.'/../../src/Grid/Filter/In.php',
       ];

       foreach ($files as $file) {
           $content = file_get_contents($file);
           expect($content)->not->toContain('<<<HTML')
               ->not->toContain('<<<');
       }
   });
   ```

### 6.3 Tests to Delete
**None.**

---

## 7. Verification

The Backend and Frontend Engineers must run:

```bash
# 1. Full test & static analysis gate
composer test

# 2. Verify all characterisation tests pass
vendor/bin/pest tests/Unit/GridFilterFieldRenderTest.php

# 3. Structural assertion: No heredocs in src/Grid/Filter/
grep -rn '<<<' src/Grid/Filter/
# Expected: no output (exit code 1)

# 4. Security assertion: No unescaped Blade tags in resources/views/
grep -rn '{!!' resources/views/
# Expected: no output (exit code 1)
```

---

## 8. Risks & Mitigations

| Risk | Impact | Mitigation |
| :--- | :--- | :--- |
| Extra whitespace before `>` in `<option>` elements | Breaks characterisation assertions `toContain('<option value="1">Admin</option>')` | Do NOT use `@selected()`; use inline ternary attribute output `<option value="{{ $key }}"{{ ... ? ' selected' : '' }}>` |
| View not found in standalone tests | Filter fields return empty string or error | Use `function_exists('view') && view()->exists(...)` check in `render()`. Package views are automatically registered by `AdminServiceProvider::boot()`. |
| Breaking 100% type coverage | `composer test:types` fails | Fully type all methods (`defaultVariables(): array`, `newProxy(): object`, `render(): string`, etc.) with strict parameter, return types, and PHPDocs. |
| Accidental recursive call if `$this` is passed to view | Server hangs / stack overflow segfault | Pass `$this->newProxy()` which does NOT implement `Htmlable` or `Stringable`. |
| Proxy class accessing protected `Field` property | Fatal error (`Cannot access protected property`) | Anonymous class is not a subclass of `Field`. Templates must use public getters (e.g. `$field->getId()`). Proxy `__get` falls back to `get{Name}()` or throws explicit exception. |
| `Filter.php:389` legacy proxy protected property trap | `isset($filter)` in `filter.blade.php` is false | Known legacy issue recorded; harmless currently because `$fields` is explicitly passed. Documented for future refactoring. |
| `Field::attributes()` custom HTML attributes | Omitted from DOM output | Known out-of-scope; existing code before refactor did not emit custom attributes either. Behavior is strictly preserved. |
