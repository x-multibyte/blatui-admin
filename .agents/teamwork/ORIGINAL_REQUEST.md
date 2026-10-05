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


## 2026-10-04T18:37:38Z

This is a single self-contained fix; keep it small and focused. Implement package foundations (Exceptions, Global Logging, and Container-driven Grid/Form lifecycle hooks) for `x-multibyte/blatui-admin` adhering strictly to `docs/superpowers/specs/2026-10-05-foundations-exceptions-logging-events-design.md` and executing the deterministic `.agents/workflows/spec-driven-tdd.js` workflow.

Working directory: /laravel/packages/x-multibyte/blatui-admin
Integrity mode: development

## Requirements

### R1. Exception Handling Subsystem
Implement the `BlatUI\Admin\Exceptions` hierarchy rooted at `AdminException extends \RuntimeException implements \Throwable`:
- Self-rendering (`render(Request $request): Response`): returns standardized JSON (`{"status": false, "message": "...", "code": ..., "errors": [...]}`) for `expectsJson()` requests; renders `blatui-admin::errors.page` within standard admin shell for web requests.
- Specialized domain exceptions: `PermissionDeniedException` (403), `ResourceNotFoundException` (404), `FormValidationException` (422), `ConfigurationException` (500).
- Blade error template (`resources/views/errors/page.blade.php`) strictly adhering to `AGENTS.md` (zero `{!! !!}`, native `{{ }}` escaping, Tailwind CSS v4 styling).

### R2. Global Runtime Logging Subsystem
Implement `BlatUI\Admin\Support\Logger` wrapping `Psr\Log\LoggerInterface`:
- Configuration schema in `config/blatui-admin.php` (`logging.enable`, `logging.channel`, `logging.level`).
- Context auto-enrichment on every log call (`admin_user_id`, `admin_user`, `ip`, `method`, `path`).
- Public facade/entrypoints: `Admin::logger()` and `Admin::log(string $level, string $message, array $context = [])`.
- Strategic telemetry instrumentation in `AuthController`, `Permission` middleware, and `ResourceController` error points.

### R3. Container-Driven Lifecycle Hooks
Refactor `Grid::make()` and `Form::make()` to resolve through Laravel's Service Container (`Container::getInstance()->make(static::class, ...)`):
- Implement Dcat-style fluent static sugar: `Grid::resolving()`, `Grid::resolved()`, `Form::resolving()`, `Form::resolved()`.
- Directly delegate to Laravel container callbacks `Container::getInstance()->resolving()` and `Container::getInstance()->afterResolving()`.
- Zero custom Context dictionaries and zero redundant empty event classes.

## Acceptance Criteria

### Test-Driven Verification & Quality Gates
- [ ] Phase 1 & 2 (The Board & Red Light Check): Test cases defined in spec and verified RED (failing) against current codebase before any production code is touched.
- [ ] Phase 3 (Implementation): Production code turns all target tests GREEN without altering the tests.
- [ ] Phase 4 (Gate Verification): Full `composer test` passes with 0 PHPStan errors, Pint clean, 100% Type Coverage, and all Pest feature/unit suites green.
- [ ] Blade Security Invariant: `grep -rn '{!!' resources/views` yields zero matches.
