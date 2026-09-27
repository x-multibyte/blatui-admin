# Rendering Architecture Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the BlatUI Admin rendering engine to eliminate implicit XSS vulnerabilities by decoupling PHP from HTML concatenation, enforcing native Laravel `Htmlable` contracts, and utilizing Service Providers for top-level view interception.

**Architecture:** Shift from Dcat's string-concatenation God Classes to strictly typed View Models. PHP components (`Content`, `Filter`, `Tools`, etc.) must implement `Htmlable` to safely render via Blade's `{{ $component }}`. `AdminServiceProvider` registers View Composers for the final validation of top-level variables.

**Tech Stack:** PHP 8.3, Laravel 13, Blade, Pest.

**Spec:** `docs/superpowers/specs/2026-09-27-rendering-architecture-design.md`

## Global Constraints

- Package namespace must be strictly `BlatUI\Admin`.
- Zero jQuery, zero Bootstrap, zero Livewire runtime dependencies.
- Strictly adhere to PHP 8.3+ with `declare(strict_types=1);` and 100% type coverage.
- Code style must pass `vendor/bin/pint --test` and analysis must pass `vendor/bin/phpstan analyse`.

## Review Focus

- Malicious `<script>` inputs in layout titles/descriptions safely escape to plain text output. (Pinned to Task 1 & 4)
- Grid sub-components (`Filter`, `Tools`, `Row`) render fully without leaking raw HTML strings into output unless explicitly implementing `Htmlable`. (Pinned to Task 2 & 3)
- Displayer outputs (like Badges or Buttons) still render as valid HTML without being double-escaped by Blade. (Pinned to Task 2 & 3)
- `AGENTS.md` properly documents the new architectural pattern for future developers/agents. (Pinned to Task 5)

---

### Task 1: Core Layout Refactor (ViewModel Pattern)

**Files:**
- Modify: `src/Layout/Content.php`
- Modify: `tests/Security/XssRenderingTest.php`
- Create: `resources/views/layouts/fallback.blade.php`

**Steps:**
- [x] Add failing test in `tests/Security/XssRenderingTest.php` verifying `Content` implements `Htmlable` and `Renderable`.
- [x] Run test `vendor/bin/pest tests/Security/XssRenderingTest.php` to verify it fails.
- [x] Modify `src/Layout/Content.php` to implement `\Illuminate\Contracts\Support\Htmlable` and `\Illuminate\Contracts\Support\Renderable`.
- [x] Refactor `Content::renderFallback()` to completely remove PHP string concatenation (`$html .= '<div...>'`) and instead return a view: `return view('blatui-admin::layouts.fallback', ['title' => $this->title, 'description' => $this->description, 'rows' => $this->rows])->render();`.
- [x] Add `public function toHtml(): string` returning `$this->render()`.
- [x] Create `resources/views/layouts/fallback.blade.php` using Blade `{{ $title }}` and `{{ $description }}` to natively escape content.
- [x] Run the tests and make sure they pass.
- [x] Commit with message `refactor(layout): extract Content HTML generation to Blade fallback view`

### Task 2: Native Contracts for Grid Components

**Files:**
- Modify: `src/Grid/Filter.php`
- Modify: `src/Grid/Tools.php`
- Modify: `src/Grid/Column.php`
- Modify: `tests/Unit/GridColumnTest.php`
- Modify: `tests/Feature/GridFilterToolsTest.php`

**Steps:**
- [x] Add failing tests in `tests/Feature/GridFilterToolsTest.php` ensuring `Filter` and `Tools` implement `Htmlable`.
- [x] Run the tests to make sure they fail.
- [x] Modify `src/Grid/Filter.php` and `src/Grid/Tools.php` to implement `\Illuminate\Contracts\Support\Htmlable`.
- [x] Implement `toHtml(): string` in both classes, internally delegating to their existing view rendering logic.
- [x] Ensure `src/Grid/Column.php` and `src/Grid/Row.php` properly implement `Htmlable` for their rendering scopes.
- [x] Run the tests and make sure they pass.
- [x] Commit with message `refactor(grid): implement Htmlable contract for nested Grid components`

### Task 3: View Layer Clean Up (Replacing `{!! !!}`)

**Files:**
- Modify: `resources/views/grid/table.blade.php`
- Modify: `resources/views/grid/filter.blade.php`
- Modify: `resources/views/layouts/app.blade.php`

**Steps:**
- [x] Review `resources/views/grid/table.blade.php`.
- [x] Replace `{!! $tools->render() !!}` with `{{ $tools }}`.
- [x] Replace `{!! $filter->render() !!}` with `{{ $filter }}`.
- [x] Replace `{!! $row->cell($column) !!}` with `{{ $row->cell($column) }}` (Requires `cell()` method to return `HtmlString`).
- [x] Update any corresponding `resources/views/grid/filter.blade.php` or `resources/views/layouts/app.blade.php` replacing `{!! $content !!}` with `{{ $content }}`.
- [x] Run full test suite `vendor/bin/pest` to ensure no UI functionality is broken.
- [x] Run `vendor/bin/phpstan analyse` to ensure type stability.
- [x] Commit with message `refactor(views): migrate from raw output to native Blade escaping`

### Task 4: ViewComposer Gatekeeper

**Files:**
- Create: `src/View/Composers/GridComposer.php`
- Create: `src/View/Composers/LayoutComposer.php`
- Modify: `src/AdminServiceProvider.php`
- Modify: `tests/Security/XssRenderingTest.php`

**Steps:**
- [x] Add failing test in `tests/Security/XssRenderingTest.php` asserting that View Composers automatically escape injected string variables.
- [x] Run the tests to make sure they fail.
- [x] Create `GridComposer` and `LayoutComposer` (using namespace `BlatUI\Admin\View\Composers`). Implement `compose(View $view)` to loop through view data and validate/sanitize scalar strings (or ensure they enforce strict type requirements).
- [x] Register both composers in the `boot` method of `src/AdminServiceProvider.php`.
- [x] Run `vendor/bin/pest tests/Security/XssRenderingTest.php` and make sure it passes.
- [x] Commit with message `feat(security): introduce ViewComposers as data gatekeepers`

### Task 5: Project Context Maintenance (AGENTS.md)

**Files:**
- Modify: `AGENTS.md`

**Steps:**
- [x] Edit `AGENTS.md`.
- [x] Add a new section `## Rendering Architecture & Security` detailing the conventions established in this refactor (e.g., "PHP classes must act as strictly typed ViewModels/DTOs", "Never use string concatenation for HTML", "Implement `Htmlable` and use `{{ $component }}` in Blade").
- [x] Review changes to ensure they clearly guide future Agents in building upon this architecture without reverting to legacy Dcat patterns.
- [x] Commit with message `docs: update AGENTS.md with new rendering architecture guidelines`
