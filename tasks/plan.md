# Implementation Plan: Decouple Composer Peer Dependencies to Resolve Laravel 13 Guzzle Conflict

> **Goal**: Decouple `gehrisandro/tailwind-merge-laravel` and `mallardduck/blade-lucide-icons` from `x-multibyte/blatui-admin` root `require` to `require-dev` (treating them as true peer dependencies managed on-demand by the installer with `-W`), thereby eliminating Guzzle 7/8 conflict and enabling clean installation on Laravel 13.

---

## 1. Context & Root Cause

When installing `x-multibyte/blatui-admin` on a fresh Laravel 13 application, Composer currently falls back to `v0.0.1` instead of installing `v0.4.0`.
- **Root Cause**: `blatui-admin` specifies `"gehrisandro/tailwind-merge-laravel": "^1.4"` in root `require`. That package strictly requires `"guzzlehttp/guzzle": "^7.5.1"`.
- **Conflict**: Fresh Laravel 13 locks `"guzzlehttp/guzzle": "8.2.0"`. Composer partial update cannot downgrade locked dependencies without `--with-all-dependencies` (`-W`).
- **Why v0.0.1 was picked**: `v0.0.1` did not require `tailwind-merge-laravel` and only required `illuminate/support`, making it the only installable version without altering locked dependencies.
- **Architectural Solution**: `blatui-admin` itself has no direct PHP code dependency on `tailwind-merge-laravel` or `blade-lucide-icons` in `src/`. They are peer dependencies required by BlatUI Blade components distributed into the host project. Moving them to `require-dev` and adding `-W` to `ProcessRunner::requireComposerPackages` ensures host projects install `blatui-admin` seamlessly and install peer packages on demand.

---

## 2. Architecture & Design Decisions

Documented in **ADR-008: Composer Peer Dependencies Decoupling & Adaptive Installation**.
1. **Root `composer.json`**:
   - `gehrisandro/tailwind-merge-laravel` and `mallardduck/blade-lucide-icons` moved from `"require"` to `"require-dev"`.
   - Added to `"suggest"` section for composer discovery.
2. **`ProcessRunner`**:
   - Add `bool $withAllDependencies = false` parameter to `buildComposerRequireCommand`, `requireComposerPackages`, and `getPlanForComposerRequire`.
   - When `$withAllDependencies` is true, append `-W` flag to composer command.
3. **`InstallWizard`**:
   - In `stepInstallComposerDependencies`, pass `withAllDependencies: true` when requiring missing peer packages so that any necessary host package adjustments (like Guzzle downgrade to 7.x) are resolved automatically.

---

## 3. Tasks Breakdown

### Task 1: Architectural Documentation (ADR-008 & SPEC Update)
- Create `docs/decisions/ADR-008-composer-peer-dependencies-decoupling.md`.
- Update `docs/decisions/README.md` to index ADR-008.
- Update `SPEC.md` to clarify peer package decoupling and `-W` installer behavior.
- **Acceptance Criteria**:
  - ADR-008 accurately describes Guzzle 7/8 collision context and peer dependency architecture.
  - `SPEC.md` reflects that peer packages are detected and installed with `-W` by `InstallWizard`.

### Task 2: Decouple Peer Dependencies in `composer.json`
- Edit `composer.json`:
  - Remove `gehrisandro/tailwind-merge-laravel` and `mallardduck/blade-lucide-icons` from `"require"`.
  - Add `gehrisandro/tailwind-merge-laravel` and `mallardduck/blade-lucide-icons` to `"require-dev"`.
  - Add `"suggest"` block for peer packages.
- Run `composer update --no-interaction` to verify package consistency.
- **Acceptance Criteria**:
  - `composer.json` root `"require"` only contains `php`, `anousss007/blatui`, `composer-runtime-api`, `laravel/framework`.
  - Workbench and dev tests still have access to peer packages via `"require-dev"`.

### Task 3: Support `--with-all-dependencies` (-W) in `ProcessRunner`
- Update `src/Console/Installer/Actions/ProcessRunner.php`:
  - Add `bool $withAllDependencies = false` to `buildComposerRequireCommand`.
  - Add `bool $withAllDependencies = false` to `requireComposerPackages`.
  - Add `bool $withAllDependencies = false` to `getPlanForComposerRequire`.
- Update `tests/Unit/Installer/Actions/ProcessRunnerTest.php`:
  - Add test verifying `-W` is appended when `$withAllDependencies` is true.
  - Verify existing tests pass.
- **Acceptance Criteria**:
  - Command array includes `-W` when requested.
  - Plan string includes `-W` when requested.

### Task 4: Integrate `-W` in `InstallWizard` Composer Step
- Update `src/Console/Installer/Wizard/InstallWizard.php`:
  - In `stepInstallComposerDependencies`, pass `withAllDependencies: true` to `$this->runner->getPlanForComposerRequire` and `$this->runner->requireComposerPackages`.
- Update `tests/Unit/Installer/Wizard/InstallWizardTest.php`:
  - Verify planned actions and execution calls include `-W`.
- **Acceptance Criteria**:
  - `blatui:admin --install --dry-run` plans `composer require ... -W`.
  - Unit tests in `InstallWizardTest` pass cleanly.

### Task 5: Full Quality Gates & Host Verification
- Run `composer test` (Pint, PHPStan Level 7, Pest 100% Type Coverage, 100% passing tests).
- Verify in host project that `blatui-admin` installs without version downgrade or Guzzle collision.
- **Acceptance Criteria**:
  - 100% test pass rate, 0 PHPStan errors, 100% type coverage.

---

## 4. Verification Checkpoint
- `composer test` passes all 4 quality gates.
- Fresh Laravel 13 can require `x-multibyte/blatui-admin` resolving to the latest release instead of `v0.0.1`.
