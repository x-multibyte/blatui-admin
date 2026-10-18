# ADR-001: Four-Tier Installer Lifecycle Pipeline

## Status
Accepted

## Date
2026-10-18

## Context
Prior to `v0.1.0`, the package installer prototype relied on monolithic console command classes and cross-cutting traits (such as `InstallStep.php` and `InteractsWithEnvironment.php`). This pattern exhibited severe drawbacks in a complex Laravel package ecosystem:
1. **Coupling Environment Detection with Destructive Mutations**: Commands probed dependencies, prompted users, and mutated files concurrently. Mid-execution errors left the host application in an inconsistent, partially configured state.
2. **Impeded Headless Execution and Unit Testing**: Console I/O (`$this->ask()`, `$this->confirm()`) was tightly coupled with filesystem writes and external process runners (npm, composer, artisan). Unit tests required heavy mocking of standard input/output streams.
3. **Absence of Dry-Run Capabilities**: Developers could not inspect an authoritative report of environmental diagnostics and proposed changes before executing mutations on their codebases.

A decoupled, strictly typed, headless-testable architecture with full dry-run support was required.

## Decision
Establish a **strictly decoupled four-tier pipeline** (`Diagnostics` → `Actions` → `Wizard` → `CLI`):

1. **Tier 1: Diagnostics Layer (`src/Console/Installer/Diagnostics/`)**
   - Comprises `EnvironmentDetector`, `DiagnosticReport`, `CheckResult`, and `CheckStatus`.
   - **Strictly Read-Only**: Inspects the host application's `composer.json`, `package.json`, lockfiles, runtime binaries (Node, PHP, package managers), and frontend stack (Tailwind, Alpine, Livewire, Flux). Produces an immutable, structured diagnostic report.
2. **Tier 2: Actions Toolkit (`src/Console/Installer/Actions/`)**
   - Comprises `ProcessRunner`, `ViteWiringManager`, `StubManager`, and `ArtisanRunner`.
   - **Stateless Execution**: Each action encapsulates an isolated file-wiring operation, external process invocation, or Artisan command execution. Accepts typed parameters, returns discrete results, and contains zero console I/O.
3. **Tier 3: Wizard Orchestrator (`src/Console/Installer/Wizard/`)**
   - Comprises `InstallWizard`, `InstallContext`, and `StepResult`.
   - **Interactive & Pipeline Coordination**: Evaluates `DiagnosticReport` for fatal errors, prompts for user preferences using `laravel/prompts` (or applies CLI overrides), and schedules actions in sequence. In `--dry-run` mode, outputs the formatted diagnostic summary and action plan without mutating files.
4. **Tier 4: CLI Entrypoints (`src/Console/Commands/`)**
   - Comprises `BlatuiAdminCommand` and the compatibility proxy `AdminInstallCommand`.
   - **Thin Command Layer**: Parses CLI arguments and options, builds an immutable `InstallContext`, and delegates directly to the container-resolved `InstallWizard`.

All services are bound as container singletons in `AdminServiceProvider`, enabling container spying and mock substitution in tests.

## Alternatives Considered

### 1. Monolithic Inheritance-Based Step Pipeline
- **Description**: Retain individual step classes extending a base `InstallStep`, where each step handles its own detection, prompting, and execution in `handle()`.
- **Drawbacks**: Blurred boundaries; inability to produce a comprehensive dry-run preview before execution; high testing friction.
- **Verdict**: Rejected.

### 2. Event-Driven Pipeline
- **Description**: Dispatch installation events through Laravel's Event Dispatcher, allowing listeners to execute discrete tasks.
- **Drawbacks**: Unnecessary abstraction (YAGNI). The installer lifecycle is a strictly sequential, directed workflow. An event bus would obscure execution order and hinder static analysis.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Clear architectural boundaries; `EnvironmentDetector` and `ViteWiringManager` achieve 100% test coverage through isolated unit tests.
  - Native support for safe `--dry-run` inspection.
  - Elimination of legacy traits and monolithic steps; full conformance with PHPStan Level 7 and strict type coverage.
- **Negative**:
  - Modest increase in class count, managed cleanly through service provider dependency injection.
