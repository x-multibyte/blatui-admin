# Original User Request

## Initial Request — 2026-09-27T11:30:38Z

Complete the BlatUI Admin rendering architecture refactoring across Grid components, View templates, and ViewComposers to enforce native Laravel `Htmlable` contracts and eliminate XSS risks.

Working directory: /laravel/packages/x-multibyte/blatui-admin
Branch: feature/security-hardening
Integrity mode: development

## Context & Baseline
- Task 1 is already complete (commit `e248609` refactored `Content::renderFallback()` to Blade `layouts/fallback.blade.php` and added `XssRenderingTest`).
- Reference Plan: `docs/superpowers/plans/2026-09-27-rendering-architecture-refactor.md`
- Reference Spec: `docs/superpowers/specs/2026-09-27-rendering-architecture-design.md`

## Requirements

### R1. Native Laravel Contracts for Grid Components (Task 2)
Ensure `BlatUI\Admin\Grid\Filter`, `BlatUI\Admin\Grid\Tools`, `BlatUI\Admin\Grid\Column`, and `BlatUI\Admin\Grid\Row` implement `Illuminate\Contracts\Support\Htmlable`. Refactor `Row::cell(Column $column)` to return `Illuminate\Support\HtmlString`. Ensure displayer outputs remain safe and unescaped inside HTML strings while plain values are escaped.

### R2. Blade View Layer Modernization (Task 3)
Replace raw HTML unescaped tags `{!! $tools->render() !!}`, `{!! $filter->render() !!}`, `{!! $row->cell($column) !!}`, and layout `{!! $content !!}` with native Blade escaping `{{ $tools }}`, `{{ $filter }}`, `{{ $row->cell($column) }}`, and `{{ $content }}` across `resources/views/grid/table.blade.php`, `resources/views/grid/filter.blade.php`, and `resources/views/layouts/app.blade.php`.

### R3. ViewComposer Defensive Gatekeepers (Task 4)
Implement `GridComposer` and `LayoutComposer` under `BlatUI\Admin\View\Composers`, registered in `AdminServiceProvider`. Validate/sanitize scalar variables injected into top-level views.

### R4. Architectural Documentation Update (Task 5)
Update `AGENTS.md` with guidelines on ViewModel patterns, native contracts (`Htmlable`), avoiding PHP string concatenation for HTML, and Blade `{{ }}` usage.

## Acceptance Criteria

### Test & Analysis Pass
- [ ] `vendor/bin/pest` passes all tests with 0 failures (including `tests/Security/XssRenderingTest.php`, `tests/Feature/GridFilterToolsTest.php`, `tests/Unit/GridColumnTest.php`, `tests/Unit/GridRowTest.php`).
- [ ] `vendor/bin/phpstan analyse` passes with 0 errors.
- [ ] `vendor/bin/pint --test` passes with 0 formatting violations.

### Security & Contract Verification
- [ ] `Filter`, `Tools`, `Column`, and `Row` strictly implement `\Illuminate\Contracts\Support\Htmlable`.
- [ ] `Row::cell()` returns `\Illuminate\Support\HtmlString`.
- [ ] `table.blade.php` uses `{{ $tools }}`, `{{ $filter }}`, and `{{ $row->cell($column) }}` without breaking UI rendering or double-escaping displayers (badges, links, images).
## Follow-up — 2026-09-27T11:39:40Z

The server has restarted. Please resume project orchestration for the BlatUI Admin rendering architecture refactor (M1 through M5). Check the status of your orchestrator subagent and continue execution.
