# ADR-005: Command Namespace Migration and Compatibility Proxy

## Status
Accepted

## Date
2026-10-18

## Context
Initial development versions registered the installer command under `admin:install`. In the broader Laravel package ecosystem, this naming posed several issues:
1. **Namespace Collision Risk**: The generic `admin:*` prefix easily conflicts with other administrative tools (such as Filament, Nova, or custom application commands).
2. **Lack of Vendor Identity**: Package commands should reside under the package namespace (`blatui:admin`) to ensure clarity and avoid accidental collisions.
3. **Limited Command Grouping**: A single `admin:install` cannot accommodate future administrative subcommands or maintenance tools.

However, abruptly removing `admin:install` would break existing workflows, documentation, and automated CI scripts.

## Decision
Adopt a **canonical primary command alongside a zero-logic compatibility proxy**:

1. **Canonical Primary Command (`BlatuiAdminCommand`)**:
   - Registered as `php artisan blatui:admin {--install} {--dry-run} {--force} {--fresh} {--install-apex-charts}`.
   - Without `--install`, prints package information and operational guidance.
   - With `--install`, constructs an `InstallContext` and invokes `InstallWizard::run()`.
2. **Backwards-Compatible Delegation Proxy (`AdminInstallCommand`)**:
   - Retained as `php artisan admin:install`.
   - Strip all business logic and transform it into a thin compatibility proxy:
     - Forwards all CLI flags (`--dry-run`, `--force`, `--fresh`, `--install-apex-charts`) to `InstallContext` with `install=true`.
     - Delegates directly to `InstallWizard`, executing the identical lifecycle pipeline.

## Alternatives Considered

### 1. Immediate Hard Removal of `admin:install`
- **Description**: Unregister the old command immediately.
- **Drawbacks**: Breaks existing user setups and CI scripts without a deprecation window.
- **Verdict**: Rejected.

### 2. Dual Parallel Implementations
- **Description**: Maintain duplicate execution logic in both command classes.
- **Drawbacks**: Violates DRY; creates maintenance burden and risks logic drift between the two commands.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Establishes a standard `blatui:admin` namespace, eliminating naming collisions.
  - Guarantees 100% backwards compatibility for existing workflows.
  - Centralizes execution logic strictly inside `InstallWizard`.
- **Negative**:
  - Maintains a small compatibility class (fewer than 25 lines of code) until a future major release deprecates it.
