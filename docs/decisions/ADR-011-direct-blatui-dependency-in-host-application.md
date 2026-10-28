# ADR-011: Direct BlatUI Host Dependency Installation on `admin:install`

## Status
Accepted

## Date
2026-10-29

## Context
`x-multibyte/blatui-admin` provides the `admin:install` artisan command (and its canonical form `blatui:admin --install`) to scaffold and configure the BlatUI administration panel.

Historically, while `x-multibyte/blatui-admin` required `anousss007/blatui` in its own `composer.json`, the host Laravel project did not explicitly declare `anousss007/blatui` in its root `composer.json`. It only existed transitively in `composer.lock`.

This indirect arrangement introduced several systemic complications ("量身打造的枷锁"):
1. **AI Context & Skill Discovery Invalidation (`laravel/boost`)**: Per ADR-010, `laravel/boost` intentionally restricts package skill discovery to direct host dependencies (`$package->isDirect()`). Transitive packages are ignored. Because `anousss007/blatui` was not direct, AI assistants could not natively discover the underlying `blatui-development` Blade component skills.
2. **First-Class Project Ownership (`blatui:add`)**: The host project uses BlatUI components (`<x-ui.*>`) and artisan generators (`php artisan blatui:add`). Treating BlatUI as an indirect dependency placed an artificial burden on `blatui-admin` to act as an intermediary runtime proxy.
3. **Core Mandate of the Installer ("只负责安装")**: An installer command's sole responsibility is installation. When a developer executes `admin:install`, the installer should directly equip the host project with its required packages.

## Decision
1. **Direct Package Detection in Diagnostics (`EnvironmentDetector`)**:
   - Introduce `EnvironmentDetector::hasDirectComposerPackage(string $package): bool`, inspecting exclusively the host project's root `composer.json` (`require` or `require-dev`), ignoring transitive lock files or runner runtime.
   - Update `checkBlatUI()` to evaluate whether `anousss007/blatui` is directly declared.
2. **Enforce Direct Installation in Wizard (`InstallWizard`)**:
   - In `InstallWizard::requiredComposerPackages()`, check direct presence of `anousss007/blatui`.
   - If not directly present in the host's `composer.json`, append `anousss007/blatui:^1.36` to the Composer require queue.
   - In both `--dry-run` and live execution, run `composer require anousss007/blatui:^1.36 ... -W` using `ProcessRunner`.

## Consequences
- **Positive**:
  - The host Laravel project becomes a genuine BlatUI application with explicit dependency ownership.
  - `laravel/boost` natively discovers both `blatui-development` and `blatui-admin-development` skills.
  - Developers can run `php artisan blatui:add` directly without relying on administrative shims.
  - The installer remains focused on its true responsibility: installation.
- **Negative / Trade-offs**:
  - Installing `blatui-admin` on a fresh project via `admin:install` executes an additional package requirement in the host `composer.json`, which is the intended behavior.
