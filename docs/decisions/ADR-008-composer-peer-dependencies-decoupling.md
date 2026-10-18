# ADR-008: Composer Peer Dependencies Decoupling & Adaptive Installation

## Status
Accepted

## Date
2026-10-18

## Context
When installing `x-multibyte/blatui-admin` into a newly scaffolded Laravel 13 application via `composer require x-multibyte/blatui-admin`, Composer was observed to unexpectedly downgrade and select version `0.0.1` rather than the latest release (`v0.4.0`).

Investigation revealed a classic SAT-solver conflict on transitive dependencies:
1. `x-multibyte/blatui-admin` declared `"gehrisandro/tailwind-merge-laravel": "^1.4"` and `"mallardduck/blade-lucide-icons": "^2.0"` in its root `composer.json` `"require"` block.
2. `gehrisandro/tailwind-merge-laravel: v1.4.0` strictly requires `"guzzlehttp/guzzle": "^7.5.1"`, with no support for Guzzle 8.x in its published constraints.
3. Fresh Laravel 13 projects (supporting both Guzzle 7 and 8) lock `"guzzlehttp/guzzle": "8.2.0"` in their initial `composer.lock`.
4. By default, `composer require <pkg>` performs a partial update without modifying locked dependencies. Because locked Guzzle 8 cannot be silently downgraded to Guzzle 7, all releases of `blatui-admin` from `v0.0.2` to `v0.4.0` were rejected by Composer's solver.
5. Composer then backtracked to `v0.0.1`—the only historical release that did not require `tailwind-merge-laravel`—and silently installed it.

Crucially, `x-multibyte/blatui-admin` has **no direct PHP code dependencies on `tailwind-merge-laravel` or `blade-lucide-icons` within its own `src/` codebase**. They are required strictly by the BlatUI Blade UI components when copied into the host application via `php artisan blatui:add` (e.g. `twMerge()` macro and `<x-lucide-...>` icon tags). In fact, the upstream `anousss007/blatui` package itself does not enforce them in its root `require`, but treats them as peer dependencies.

## Decision
Decouple secondary UI packages into **true peer dependencies** managed by the installation engine:

1. **Root `composer.json` Decoupling**:
   - Move `gehrisandro/tailwind-merge-laravel` and `mallardduck/blade-lucide-icons` from `"require"` to `"require-dev"`.
   - Keep them in `"require-dev"` so that the package's internal workbench and unit tests continue to validate against them.
   - Add them to `"suggest"` in `composer.json` with descriptive explanation per Composer standards.
2. **Adaptive Installation with `--with-all-dependencies` (`-W`)**:
   - Enhance `ProcessRunner` to support a `$withAllDependencies` flag on `buildComposerRequireCommand`, `requireComposerPackages`, and `getPlanForComposerRequire`.
   - In `InstallWizard::stepInstallComposerDependencies`, invoke `requireComposerPackages` with `withAllDependencies: true`.
   - When the user runs `php artisan blatui:admin --install`, the installer sniffs peer packages via `EnvironmentDetector::checkPeerPackages()`. If missing, it installs them using `composer require ... -W`, allowing Composer to safely negotiate any required transitive adjustments (such as aligning Guzzle versions).

## Alternatives Considered

### 1. Maintain Hard `require` and Instruct Users to Run `composer require -W`
- **Pros**: Zero package code changes.
- **Cons**: Severe onboarding friction; users unaware of `-W` will silently receive `v0.0.1` or encounter resolution errors when trying to adopt the package.
- **Verdict**: Rejected.

### 2. Fork or Vendor `tailwind-merge-laravel`
- **Pros**: Eliminates external Guzzle dependency entirely.
- **Cons**: High maintenance overhead; diverges from standard BlatUI conventions and documentation.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - `x-multibyte/blatui-admin` installs cleanly on any Laravel 12 or 13 application via standard `composer require x-multibyte/blatui-admin` without solver backtracking.
  - Aligns package architecture with `SPEC.md`, which already designated these packages as peer packages.
  - Retains automated, hands-off installation via `blatui:admin --install` with `-W` flexibility.
- **Negative**:
  - Host applications will not have `twMerge()` or Lucide icons until either `blatui:admin --install` is executed or the packages are installed manually. (This is already the intended design for `blatui:admin`).
