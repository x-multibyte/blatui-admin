# Dispatch Log

## 2026-10-04T18:39:16Z
You are the SWE Light Orchestrator for the BlatUI Admin package foundations task.

Your working directory is:
/laravel/packages/x-multibyte/blatui-admin/.agents/teamwork/swe_1

The project root is:
/laravel/packages/x-multibyte/blatui-admin

Authoritative request:
/laravel/packages/x-multibyte/blatui-admin/.agents/teamwork/ORIGINAL_REQUEST.md

Design Spec:
/laravel/packages/x-multibyte/blatui-admin/docs/superpowers/specs/2026-10-05-foundations-exceptions-logging-events-design.md

Deterministic workflow:
/laravel/packages/x-multibyte/blatui-admin/.agents/workflows/spec-driven-tdd.js

Mission & Scope:
Implement package foundations (Exceptions, Global Logging, and Container-driven Grid/Form lifecycle hooks) for `x-multibyte/blatui-admin` adhering strictly to the design specification and executing the deterministic spec-driven TDD workflow.

Key Requirements:
1. Exception Handling Subsystem:
   - Implement `BlatUI\Admin\Exceptions` hierarchy rooted at `AdminException extends \RuntimeException implements \Throwable`.
   - Self-rendering (`render(Request $request): Response`): returns standardized JSON (`{"status": false, "message": "...", "code": ..., "errors": [...]}`) for `expectsJson()` requests; renders `blatui-admin::errors.page` within standard admin shell for web requests.
   - Specialized domain exceptions: `PermissionDeniedException` (403), `ResourceNotFoundException` (404), `FormValidationException` (422), `ConfigurationException` (500).
   - Blade error template (`resources/views/errors/page.blade.php`) strictly adhering to `AGENTS.md` (zero `{!! !!}`, native `{{ }}` escaping, Tailwind CSS v4 styling).

2. Global Runtime Logging Subsystem:
   - Implement `BlatUI\Admin\Support\Logger` wrapping `Psr\Log\LoggerInterface`.
   - Configuration schema in `config/blatui-admin.php` (`logging.enable`, `logging.channel`, `logging.level`).
   - Context auto-enrichment on every log call (`admin_user_id`, `admin_user`, `ip`, `method`, `path`).
   - Public facade/entrypoints: `Admin::logger()` and `Admin::log(string $level, string $message, array $context = [])`.
   - Strategic telemetry instrumentation in `AuthController`, `Permission` middleware, and `ResourceController` error points.

3. Container-Driven Lifecycle Hooks:
   - Refactor `Grid::make()` and `Form::make()` to resolve through Laravel's Service Container (`Container::getInstance()->make(static::class, ...)`).
   - Implement Dcat-style fluent static sugar: `Grid::resolving()`, `Grid::resolved()`, `Form::resolving()`, `Form::resolved()`.
   - Directly delegate to Laravel container callbacks `Container::getInstance()->resolving()` and `Container::getInstance()->afterResolving()`.
   - Zero custom Context dictionaries and zero redundant empty event classes.

Quality Gates & Acceptance Criteria:
- Phase 1 & 2 (The Board & Red Light Check): Test cases defined in spec and verified RED (failing) against current codebase before any production code is touched.
- Phase 3 (Implementation): Production code turns all target tests GREEN without altering the tests.
- Phase 4 (Gate Verification): Full `composer test` passes with 0 PHPStan errors, Pint clean, 100% Type Coverage, and all Pest feature/unit suites green.
- Blade Security Invariant: `grep -rn '{!!' resources/views` yields zero matches.

Maintain your `progress.md` and `BRIEFING.md` continuously in `/laravel/packages/x-multibyte/blatui-admin/.agents/teamwork/swe_1/`.
When complete, notify the caller via send_message with a complete summary of test results and changes.
