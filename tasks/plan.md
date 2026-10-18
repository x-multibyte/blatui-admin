# Implementation Plan: BlatUI Admin Lifecycle & Dependency Management Engine

> **Goal**: Refactor the installer for `x-multibyte/blatui-admin` into a high-cohesion, testable, environment-adaptive four-tier engine (`Diagnostics`, `Actions`, `Wizard`, `CLI`), compliant with `SPEC.md`.

---

## 1. Context

The legacy `src/Console/Commands/AdminInstallCommand.php` suffered from monolithic script patterns:
1. **Entangled Detection and Execution**: Environment detection logic was scattered across `InteractsWithEnvironment` trait and closures, preventing clean diagnostic reporting for `--dry-run` and interactive flows.
2. **Lack of Action Abstractions**: Direct calls to `file_put_contents`, `Process::run`, and child commands were coupled with UI prompts, impeding unit testing.
3. **Inconsistent Console UI**: Mixed usage of `Command::$components` and `laravel/prompts` violated the single-channel console UI standard.
4. **Insufficient Host Protection**: Lacked robust protections against duplicate-Alpine collisions (Livewire/Flux), Tailwind v3 vs v4 handling, opt-in ApexCharts isolation, and component code-ownership workflows (`blatui:add`).

This implementation plan structured the refactoring into five ordered phases:
`diagnostics` → `actions` → `wizard` → `cli` → `cleanup & verification`.

---

## 2. Architecture & Dependencies

```
┌─────────────────────────────────────────────────────────────┐
│                    CLI Commands Layer                       │
│  - BlatuiAdminCommand (`php artisan blatui:admin --install`) │
│  - AdminInstallCommand (`php artisan admin:install` proxy)  │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                       Wizard Layer                          │
│  - InstallContext (Runtime options & user preferences DTO)  │
│  - StepResult (Discrete step execution outcome DTO)         │
│  - InstallWizard (laravel/prompts orchestrator)             │
└──────────────┬──────────────────────────────┬───────────────┘
               │                              │
               ▼                              ▼
┌──────────────────────────────┐ ┌────────────────────────────┐
│      Diagnostics Layer       │ │       Actions Layer        │
│  - CheckStatus (Enum)        │ │  - ProcessRunner (Process) │
│  - CheckResult (DTO)         │ │  - ViteWiringManager (CSS) │
│  - DiagnosticReport (Report) │ │  - StubManager (Stubs)     │
│  - EnvironmentDetector       │ │  - ArtisanRunner (Artisan) │
└──────────────────────────────┘ └────────────────────────────┘
```

**Unidirectional Dependency Flow**:
- `Diagnostics` and `Actions` are mutually independent, headless primitives.
- `Wizard` orchestrates `Diagnostics` (read-only inspection) and `Actions` (side-effect execution).
- `CLI Commands` act as thin entrypoints delegating to `InstallWizard`.

---

## 3. Phased Tasks

### Phase 1: Diagnostics Pre-flight & Reporting System
**Responsibility**: Read-only environment sniffing and structured diagnostic reporting.

1. **`CheckStatus.php`** (`src/Console/Installer/Diagnostics/CheckStatus.php`):
   - PHP 8.3 pure enum: `PASS`, `WARN`, `FAIL`.
   - Methods: `label(): string`, `icon(): string`.
2. **`CheckResult.php`** (`src/Console/Installer/Diagnostics/CheckResult.php`):
   - Immutable DTO: `name`, `status`, `message`, `suggestedFix`.
3. **`DiagnosticReport.php`** (`src/Console/Installer/Diagnostics/DiagnosticReport.php`):
   - Report container holding `array<int, CheckResult>`.
   - Methods: `add()`, `hasCriticalFailures()`, `hasWarnings()`, `toTableRows()`.
4. **`EnvironmentDetector.php`** (`src/Console/Installer/Diagnostics/EnvironmentDetector.php`):
   - Detects Composer dependencies, Node dependencies, runtime binaries (PHP >= 8.3, Node >= 18), lockfiles, and frameworks.
   - Aggregate method: `fullDiagnostics(): DiagnosticReport`.
5. **Unit Tests & Verification**:
   - `tests/Unit/Installer/Diagnostics/CheckStatusTest.php`
   - `tests/Unit/Installer/Diagnostics/CheckResultTest.php`
   - `tests/Unit/Installer/Diagnostics/DiagnosticReportTest.php`
   - `tests/Unit/Installer/Diagnostics/EnvironmentDetectorTest.php`
   - Completed in PR #7 (Release `v0.1.0`).

---

### Phase 2: Actions Execution Toolkit
**Responsibility**: Encapsulate file operations, external processes, and Artisan subcommands into testable units.

1. **`ProcessRunner.php`** (`src/Console/Installer/Actions/ProcessRunner.php`):
   - Encapsulates Laravel `Process` Facade for npm, pnpm, yarn, bun, npx, and composer.
2. **`ViteWiringManager.php`** (`src/Console/Installer/Actions/ViteWiringManager.php`):
   - Additive CSS injection compliant with W3C / Lightning CSS `@import` ordering.
   - Intelligent JS wiring avoiding duplicate Alpine runtimes across Greenfield, Livewire, and Flux setups.
3. **`ArtisanRunner.php`** (`src/Console/Installer/Actions/ArtisanRunner.php`):
   - Safe execution of `vendor:publish`, `migrate`, and `blatui:add`.
4. **`StubManager.php`** (`src/Console/Installer/Actions/StubManager.php`):
   - Component directory verification and conflict detection.
5. **Unit Tests & Verification**:
   - `tests/Unit/Installer/Actions/ProcessRunnerTest.php`
   - `tests/Unit/Installer/Actions/ViteWiringManagerTest.php`
   - `tests/Unit/Installer/Actions/ArtisanRunnerTest.php`
   - `tests/Unit/Installer/Actions/StubManagerTest.php`
   - Completed in PR #8 (Release `v0.2.0`).

---

### Phase 3: Wizard Orchestrator & Context
**Responsibility**: Interactive flow orchestration and dry-run execution using `laravel/prompts`.

1. **`InstallContext.php`** (`src/Console/Installer/Wizard/InstallContext.php`):
   - Immutable DTO for flags: `dryRun`, `force`, `fresh`, `interactive`, `composerPath`, `includeApexCharts`, `selectedComponents`.
2. **`StepResult.php`** (`src/Console/Installer/Wizard/StepResult.php`):
   - Encapsulates step outcomes and dry-run planned actions.
3. **`InstallWizard.php`** (`src/Console/Installer/Wizard/InstallWizard.php`):
   - Orchestrates diagnostics rendering, preference collection, and sequential action dispatch wrapped in `spin()`.
4. **Unit Tests & Verification**:
   - `tests/Unit/Installer/Wizard/InstallContextTest.php`
   - `tests/Unit/Installer/Wizard/StepResultTest.php`
   - `tests/Unit/Installer/Wizard/InstallWizardTest.php`
   - Completed in PR #9 (Release `v0.3.0`).

---

### Phase 4: CLI Commands & Compatibility
**Responsibility**: Register canonical commands and backwards-compatible proxies.

1. **`BlatuiAdminCommand.php`** (`src/Console/Commands/BlatuiAdminCommand.php`):
   - Canonical signature: `blatui:admin {--install} {--dry-run} {--fresh} {--force} {--install-apex-charts}`.
2. **`AdminInstallCommand.php`** (`src/Console/Commands/AdminInstallCommand.php`):
   - Compatibility proxy forwarding options directly to `InstallWizard`.
3. **`AdminServiceProvider.php`** (`src/AdminServiceProvider.php`):
   - Registers commands and binds installer singletons in the service container.
4. **Feature Tests & Verification**:
   - `tests/Feature/Commands/BlatuiAdminCommandTest.php`
   - `tests/Feature/Commands/AdminInstallCommandTest.php`
   - Completed in PR #10 (Release `v0.4.0`).

---

### Phase 5: Cleanup & Full Quality Gates
**Responsibility**: Remove legacy monolithic code and verify complete test suite.

1. **Legacy File Removal**:
   - Purged `src/Support/InstallStep.php`.
   - Purged `src/Console/Commands/Concerns/InteractsWithEnvironment.php`.
   - Purged `tests/Feature/Install/EnvironmentDetectionTest.php`.
2. **Four-Dimensional Quality Gates**:
   - `composer lint:check` (Pint code style)
   - `composer analyse` (PHPStan Level 7)
   - `composer test:types` (100% Type Coverage)
   - `composer test:unit` (Pest test suite)
   - `composer test` (Full CI pipeline)
   - Completed in PR #10 (Release `v0.4.0`).

---

## 4. Deliverables & Verification Status

- [x] `php artisan blatui:admin --install --dry-run` outputs formatted pre-flight report and action plan with exit code 0.
- [x] `php artisan admin:install --dry-run` operates identically as a backwards-compatible alias.
- [x] `EnvironmentDetector` correctly identifies Greenfield, Livewire, Flux, Tailwind v3/v4, and diverse lockfiles.
- [x] `ViteWiringManager` performs idempotent, non-destructive CSS and JS insertions across all supported environments.
- [x] Legacy dead code is completely purged.
- [x] `composer test` passes all quality gates with zero errors and 100% type coverage.
