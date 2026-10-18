# Task List: BlatUI Admin Lifecycle & Dependency Management Engine

- [x] **Phase 1: Diagnostics Layer**
  - [x] 1.1 Create `CheckStatus` enum (`PASS`, `WARN`, `FAIL` with labels and icons)
  - [x] 1.2 Create `CheckResult` immutable DTO (`name`, `status`, `message`, `suggestedFix`)
  - [x] 1.3 Create `DiagnosticReport` aggregate model (result collection, counters, `toTableRows()`)
  - [x] 1.4 Create `EnvironmentDetector` service (Composer, Node, lockfiles, Livewire, Flux, Tailwind)
  - [x] 1.5 Write and pass Phase 1 unit tests (`CheckStatusTest`, `CheckResultTest`, `DiagnosticReportTest`, `EnvironmentDetectorTest`)

- [x] **Phase 2: Actions Toolkit**
  - [x] 2.1 Create `ProcessRunner` (npm/pnpm/yarn/bun/npx/composer execution wrapper)
  - [x] 2.2 Create `ViteWiringManager` (Tailwind v4 `@import` additive wiring and 3-way JS collision-free wiring)
  - [x] 2.3 Create `ArtisanRunner` (safe execution of `vendor:publish`, `migrate`, `blatui:add`)
  - [x] 2.4 Create `StubManager` (component directory setup and conflict detection)
  - [x] 2.5 Write and pass Phase 2 unit tests (`ProcessRunnerTest`, `ViteWiringManagerTest`, `ArtisanRunnerTest`, `StubManagerTest`)

- [x] **Phase 3: Wizard Orchestrator**
  - [x] 3.1 Create `InstallContext` DTO (dryRun, force, fresh, interactive, apexcharts, components)
  - [x] 3.2 Create `StepResult` DTO (step success, title, message, planned actions)
  - [x] 3.3 Create `InstallWizard` (`laravel/prompts` UI, tables, prompts, spinners, outro)
  - [x] 3.4 Write and pass Phase 3 unit tests (`InstallContextTest`, `StepResultTest`, `InstallWizardTest`)

- [x] **Phase 4: CLI Commands**
  - [x] 4.1 Create `BlatuiAdminCommand` canonical command (`php artisan blatui:admin {--install}`)
  - [x] 4.2 Refactor `AdminInstallCommand` as backwards-compatible delegation proxy (`php artisan admin:install`)
  - [x] 4.3 Register commands and bind services in `AdminServiceProvider`
  - [x] 4.4 Write and pass Feature tests (`BlatuiAdminCommandTest`, `AdminInstallCommandTest`)

- [x] **Phase 5: Cleanup & Verification**
  - [x] 5.1 Remove legacy files (`InstallStep.php`, `InteractsWithEnvironment.php`, `EnvironmentDetectionTest.php`)
  - [x] 5.2 Run Pint code style check and fix (`composer lint`)
  - [x] 5.3 Run PHPStan static analysis (`composer analyse`)
  - [x] 5.4 Verify 100% Type Coverage (`composer test:types`)
  - [x] 5.5 Run full Pest test suite (`composer test:unit`)
  - [x] 5.6 Run complete CI pipeline (`composer test`)
