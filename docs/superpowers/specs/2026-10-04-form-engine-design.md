> **Status: IN PROGRESS**
> **Authority:** This document defines the implementation specification for the Form Builder DSL Core Engine (Phase 1) in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md`.

# Form 表单构造器 DSL 核心引擎 (Phase 1) — Implementation Specification

## 1. Objective

Implement the core Form engine (Phase 1) for BlatUI Admin. This engine provides a modern, fluent DSL (`Form::make($repository, Closure $callback = null)`) to construct and render admin forms with core field types (`Text`, `Textarea`, `Select`, `SwitchField`, `Datetime`, `Hidden`, `Display`).

The architecture strictly adheres to `AGENTS.md` and the established rendering contract:
- PHP classes (`Form`, `Field`, `Layout`, etc.) act strictly as ViewModels / DTOs. Zero HTML concatenation, zero heredocs (`<<<`), zero inline JS.
- All DOM markup, Tailwind CSS classes, and Alpine.js interactions reside exclusively in Blade views under `resources/views/form/` using the package view namespace `blatui-admin::form.*`.
- Native Blade escaping `{{ }}` is the sole security boundary. Raw output `{!! !!}` is strictly forbidden.
- Components that render implement `\Illuminate\Contracts\Support\Htmlable` and `\Illuminate\Contracts\Support\Renderable`.
- Form lifecycle hooks (`saving`, `saved`, `creating`, `created`, `updating`, `updated`) and Repository integration (`src/Contracts/Repository.php`) handle data retrieval, validation, and storage.

---

## 2. Current State (Evidenced)

1. **Absence of Form Engine**:
   - `src/Form.php` and directory `src/Form/` do not currently exist in the repository.
   - `resources/views/form/` does not exist.
2. **Established ViewModel Patterns**:
   - `src/Grid.php` (lines 531-615) and `src/Layout/Content.php` implement `Htmlable` and delegate rendering directly to `view('blatui-admin::...')->render()`.
   - `src/Grid/Filter/Field.php` passes an anonymous proxy or pure variables into Blade templates to prevent recursive rendering loops.
3. **Repository Integration**:
   - `src/Contracts/Repository.php` defines the standard contract:
     - `getEditData($id): array|Model|null`
     - `store(array $attributes): mixed`
     - `update($id, array $attributes): bool`
     - `delete($id): bool`
   - `src/Repositories/EloquentRepository.php` provides the Eloquent implementation.
4. **View Namespace**:
   - `src/AdminServiceProvider.php:41` registers package views under the namespace `blatui-admin`:
     `$this->loadViewsFrom(__DIR__.'/../resources/views', 'blatui-admin');`

---

## 3. Target State & Architecture

### 3.1 Class Architecture

```
src/
├── Form.php                           # Main Form DSL builder & ViewModel (implements Htmlable, Renderable, Responsable)
└── Form/
    ├── Field.php                      # Abstract Field ViewModel (implements Htmlable, Renderable, Stringable)
    ├── Field/
    │   ├── Text.php                   # Text input field
    │   ├── Textarea.php               # Textarea field
    │   ├── Select.php                 # Select dropdown field
    │   ├── SwitchField.php            # Toggle switch field
    │   ├── Datetime.php               # Date/time input field
    │   ├── Hidden.php                 # Hidden input field
    │   └── Display.php                # Readonly display field
    ├── Layout/
    │   └── Column.php                 # Form column layout wrapper (optional grid column)
    └── Lifecycle.php                  # Hook runner & event emitter
```

### 3.2 `src/Form.php` Specification

```php
namespace BlatUI\Admin;

use BlatUI\Admin\Contracts\Repository;
use BlatUI\Admin\Form\Field;
use BlatUI\Admin\Repositories\EloquentRepository;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class Form implements Htmlable, Renderable, Responsable
{
    protected Repository $repository;
    protected mixed $key = null; // ID being edited (null when creating)
    protected array $fields = []; // Field instances
    protected array $hooks = [
        'saving' => [],
        'saved' => [],
        'creating' => [],
        'created' => [],
        'updating' => [],
        'updated' => [],
    ];
    protected string $action = '';
    protected string $method = 'POST';
    protected ?string $title = null;
    protected string $view = 'blatui-admin::form.container';

    public function __construct(mixed $repository)
    {
        if ($repository instanceof Model) {
            $repository = new EloquentRepository($repository);
        }
        $this->repository = $repository;
    }

    public static function make(mixed $repository, ?Closure $callback = null): static
    {
        $form = new static($repository);
        if ($callback) {
            $callback($form);
        }
        return $form;
    }

    // Field registration methods
    public function text(string $column, ?string $label = null): Field\Text;
    public function textarea(string $column, ?string $label = null): Field\Textarea;
    public function select(string $column, ?string $label = null): Field\Select;
    public function switch(string $column, ?string $label = null): Field\SwitchField;
    public function datetime(string $column, ?string $label = null): Field\Datetime;
    public function hidden(string $column, ?string $label = null): Field\Hidden;
    public function display(string $column, ?string $label = null): Field\Display;

    // Field management
    public function pushField(Field $field): static;
    public function fields(): array;

    // Lifecycle hooks
    public function saving(Closure $callback): static;
    public function saved(Closure $callback): static;
    public function creating(Closure $callback): static;
    public function created(Closure $callback): static;
    public function updating(Closure $callback): static;
    public function updated(Closure $callback): static;

    // Data hydration & editing
    public function edit(mixed $id): static;
    public function fill(array $data): static;
    public function isCreating(): bool;
    public function isEditing(): bool;
    public function getKey(): mixed;

    // Submission & validation
    public function store(Request $request): JsonResponse|RedirectResponse;
    public function update(mixed $id, Request $request): JsonResponse|RedirectResponse;
    public function validationRules(): array;
    public function validationMessages(): array;

    // Rendering
    public function action(?string $action = null): static|string;
    public function method(?string $method = null): static|string;
    public function title(?string $title = null): static|string;
    public function render(): string;
    public function toHtml(): string;
    public function toResponse($request);
}
```

### 3.3 `src/Form/Field.php` Specification

```php
namespace BlatUI\Admin\Form;

use BlatUI\Admin\Form;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Stringable;

abstract class Field implements Htmlable, Renderable, Stringable
{
    protected string $column;
    protected string $label;
    protected mixed $value = null;
    protected mixed $defaultValue = null;
    protected ?string $help = null;
    protected ?string $placeholder = null;
    protected array|string $rules = [];
    protected array $rulesMessages = [];
    protected bool $required = false;
    protected array $attributes = [];
    protected ?Form $form = null;
    protected string $view = '';

    public function __construct(string $column, ?string $label = null)
    {
        $this->column = $column;
        $this->label = $label ?? ucfirst(str_replace(['.', '_'], ' ', $column));
    }

    // Accessors
    public function getColumn(): string;
    public function getLabel(): string;
    public function getValue(): mixed;
    public function getPlaceholder(): ?string;
    public function getHelp(): ?string;
    public function getRules(): array|string;
    public function isRequired(): bool;
    public function getView(): string;

    // Mutators & Modifiers
    public function value(mixed $value): static;
    public function default(mixed $default): static;
    public function placeholder(string $placeholder): static;
    public function help(string $help): static;
    public function rules(array|string $rules, array $messages = []): static;
    public function required(bool $required = true): static;
    public function view(string $view): static;
    public function setForm(Form $form): static;

    // Anti-recursion proxy (matches Grid Filter pattern)
    protected function newProxy(): object
    {
        return new class($this) {
            public function __construct(protected Field $field) {}
            public function __call(string $method, array $args): mixed {
                return $this->field->{$method}(...$args);
            }
            public function __get(string $name): mixed {
                $getter = 'get'.ucfirst($name);
                if (method_exists($this->field, $getter)) {
                    return $this->field->{$getter}();
                }
                throw new \InvalidArgumentException("Undefined property or getter for '{$name}' on Field proxy.");
            }
        };
    }

    // View Variables
    public function defaultVariables(): array
    {
        $rawVal = $this->getValue() ?? $this->defaultValue;
        return [
            'field' => $this->newProxy(),
            'id' => 'field_'.$this->getColumn(),
            'name' => $this->getColumn(),
            'label' => $this->getLabel(),
            'value' => is_scalar($rawVal) ? (string) $rawVal : '',
            'placeholder' => $this->getPlaceholder() ?? $this->getLabel(),
            'help' => $this->getHelp(),
            'required' => $this->isRequired(),
        ];
    }

    public function render(): string
    {
        if (function_exists('view') && view()->exists($this->view)) {
            return view($this->view, $this->defaultVariables())->render();
        }
        return '';
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
```

### 3.4 Concrete Field Classes

1. **`Text`**:
   - Extends `Field`.
   - `$view = 'blatui-admin::form.field.text'`.
2. **`Textarea`**:
   - Extends `Field`.
   - `$rows = 4`.
   - `rows(int $rows): static`.
   - `$view = 'blatui-admin::form.field.textarea'`.
3. **`Select`**:
   - Extends `Field`.
   - `protected array $options = [];`
   - `options(array $options): static`.
   - `defaultVariables()` supplies `'options' => $this->options`.
   - `$view = 'blatui-admin::form.field.select'`.
4. **`SwitchField`**:
   - Extends `Field`.
   - `protected array $states = [1 => 'ON', 0 => 'OFF'];`
   - `states(array $states): static`.
   - `$view = 'blatui-admin::form.field.switch'`.
5. **`Datetime`**:
   - Extends `Field`.
   - `protected string $format = 'Y-m-d H:i:s';`
   - `format(string $format): static`.
   - `$view = 'blatui-admin::form.field.datetime'`.
6. **`Hidden`**:
   - Extends `Field`.
   - `$view = 'blatui-admin::form.field.hidden'`.
7. **`Display`**:
   - Extends `Field`.
   - `$view = 'blatui-admin::form.field.display'`.

---

## 4. Blade Templates Specification

All Blade templates are created under `resources/views/form/`:

### 4.1 `resources/views/form/container.blade.php`

```blade
@props([
    'form' => null,
    'action' => '',
    'method' => 'POST',
    'title' => null,
    'fields' => [],
    'isEditing' => false,
])

<div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    @if ($title)
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        </div>
    @endif

    <form
        action="{{ $action }}"
        method="POST"
        class="p-6 space-y-6"
        x-data="{ submitting: false }"
        @submit="submitting = true"
    >
        @csrf
        @if ($isEditing && strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <div class="space-y-4">
            @foreach ($fields as $field)
                {{ $field }}
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
            <button
                type="button"
                onclick="window.history.back()"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="submit"
                :disabled="submitting"
                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:opacity-50 cursor-pointer"
            >
                <span x-show="!submitting">Submit</span>
                <span x-show="submitting" style="display: none;">Saving...</span>
            </button>
        </div>
    </form>
</div>
```

### 4.2 `resources/views/form/field/text.blade.php`

```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'help' => null,
    'required' => false,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="sm:col-span-3 space-y-1">
        <input
            id="{{ $id }}"
            type="text"
            name="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
```

### 4.3 `resources/views/form/field/textarea.blade.php`

```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'help' => null,
    'required' => false,
    'rows' => 4,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="sm:col-span-3 space-y-1">
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            class="flex min-h-[80px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        >{{ $value }}</textarea>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
```

### 4.4 `resources/views/form/field/select.blade.php`

```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'options' => [],
    'help' => null,
    'required' => false,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="sm:col-span-3 space-y-1">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        >
            <option value="">Please select...</option>
            @foreach ($options as $key => $optLabel)
                <option value="{{ $key }}"{{ (string) $key === (string) $value ? ' selected' : '' }}>{{ $optLabel }}</option>
            @endforeach
        </select>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
```

### 4.5 `resources/views/form/field/switch.blade.php`

```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => 0,
    'help' => null,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start" x-data="{ state: {{ (bool) $value ? 'true' : 'false' }} }">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-1">
        {{ $label }}
    </label>
    <div class="sm:col-span-3 space-y-1">
        <input type="hidden" name="{{ $name }}" :value="state ? 1 : 0" />
        <button
            type="button"
            id="{{ $id }}"
            role="switch"
            :aria-checked="state.toString()"
            @click="state = !state"
            :class="state ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
        >
            <span
                :class="state ? 'translate-x-5' : 'translate-x-0'"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
            ></span>
        </button>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
```

### 4.6 `resources/views/form/field/datetime.blade.php`

```blade
@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'help' => null,
    'required' => false,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="sm:col-span-3 space-y-1">
        <input
            id="{{ $id }}"
            type="datetime-local"
            name="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        />
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
```

### 4.7 `resources/views/form/field/hidden.blade.php`

```blade
@props([
    'name' => '',
    'value' => '',
])

<input type="hidden" name="{{ $name }}" value="{{ $value }}" />
```

### 4.8 `resources/views/form/field/display.blade.php`

```blade
@props([
    'id' => '',
    'label' => '',
    'value' => '',
    'help' => null,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">{{ $label }}</span>
    <div class="sm:col-span-3 space-y-1">
        <div class="flex min-h-9 items-center text-sm text-gray-900 dark:text-gray-100">
            {{ $value }}
        </div>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
```

---

## 5. Behavioural Impact

### Preserved
- All existing Grid, ModelTree, Layout, and Command features remain 100% operational.
- Existing 131 tests continue to pass.

### Added
- Form creation DSL via `BlatUI\Admin\Form::make($repository)`.
- Core field set (`Text`, `Textarea`, `Select`, `SwitchField`, `Datetime`, `Hidden`, `Display`).
- Form submission processing (`store` and `update` methods) with full lifecycle hooks.
- Server-side validation with `Validator::make` using field rules.

---

## 6. Test Plan

Create the following test suite under `tests/Feature/Form/`:

1. **`tests/Feature/Form/FormRenderingTest.php`**:
   - `test it renders form container with action and method`
   - `test it renders edit form with PUT method spoofing`
   - `test it renders all core field types`
   - `test it escapes field labels, values, and placeholders with Blade escaping`
   - `test field proxy inside view prevents recursive evaluation loop`
2. **`tests/Feature/Form/FormFieldTest.php`**:
   - `test text field fluent options (rules, help, placeholder, required, default)`
   - `test select field renders options and marks selected value`
   - `test textarea field supports custom rows`
   - `test switch field renders hidden input and toggle button`
   - `test display and hidden fields render correctly`
3. **`tests/Feature/Form/FormLifecycleTest.php`**:
   - `test form calls saving and creating hooks during store`
   - `test form calls saving and updating hooks during update`
   - `test form aborts store or update if a saving hook returns false or a response`
4. **`tests/Feature/Form/FormValidationTest.php`**:
   - `test form validates input according to field rules`
   - `test form store persists valid data via repository`
   - `test form update updates data via repository`
   - `test form returns json response for ajax request and redirect response for standard request`

---

## 7. Verification Gate

```bash
# 1. Full composer test gate (Pest + Pint + PHPStan + Type Coverage)
composer test

# 2. Blade security check: No raw output
grep -rn '{!!' resources/views/form
# Expected: 0 matches

# 3. Architecture check: No heredocs in Form classes
grep -rn '<<<' src/Form
# Expected: 0 matches

# 4. Zero HTML concatenation in Form classes
grep -rn '\$html .= ' src/Form
# Expected: 0 matches
```

---

## 8. Risks & Mitigations

| Risk | Impact | Mitigation |
| :--- | :--- | :--- |
| View namespace mismatch | `View [blatui::form...] not found` | Strictly use `blatui-admin::form.*` matching `AdminServiceProvider` registration. |
| Accidental recursive view call if `$field` rendered directly | Infinite loop / memory exhaustion | Use anonymous non-Htmlable proxy `newProxy()` (identical to Grid Filter pattern). |
| Switch field naming conflict with PHP keyword `switch` | Syntax error | Class is named `SwitchField`, accessed in DSL via `public function switch(...)`. |
| Breaking 100% type coverage | `composer test:types` fails | Fully type all Form and Field methods with explicit parameter types, return types, and generic PHPDocs. |
