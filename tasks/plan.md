# Technical Implementation Plan: Direct BlatUI Host Dependency Installation

## Overview
Equip the installer command (`admin:install` / `blatui:admin --install`) to detect whether `anousss007/blatui` is directly declared in the host Laravel project's `composer.json`, and if not, automatically install it using `composer require anousss007/blatui:^1.36 -W`.

## Architecture & Dependency Flow
```
[admin:install / blatui:admin --install]
                  │
                  ▼
         [InstallWizard]
                  │
                  ├── checks host composer.json via ────► [EnvironmentDetector::hasDirectComposerPackage]
                  │
                  └── if missing, passes spec to ──────► [ProcessRunner::requireComposerPackages]
                                                                  │
                                                                  ▼
                                                      composer require anousss007/blatui:^1.36 -W
```

## Implementation Phases

### Phase 1: Diagnostics Tier (`EnvironmentDetector`)
- Implement `EnvironmentDetector::hasDirectComposerPackage(string $package): bool`.
  - Reads exclusively from `$this->basePath('composer.json')`.
  - Checks `$json['require'][$package]` and `$json['require-dev'][$package]`.
  - Does NOT fall back to `composer.lock` or runner runtime (`InstalledVersions`), ensuring strictly direct dependency detection.
- Update `checkBlatUI()` in `EnvironmentDetector` to verify direct dependency presence.
- Write unit tests in `tests/Unit/Installer/Diagnostics/EnvironmentDetectorTest.php`.

### Phase 2: Wizard Tier (`InstallWizard`)
- In `InstallWizard::requiredComposerPackages()`, update the detection of `anousss007/blatui` to use `hasDirectComposerPackage()`.
- Ensure `--dry-run` outputs the planned `composer require anousss007/blatui:^1.36 ... -W`.
- Ensure execution runs `ProcessRunner::requireComposerPackages()` with `anousss007/blatui:^1.36`.
- Write/update unit tests in `tests/Unit/Installer/Wizard/InstallWizardTest.php`.

### Phase 3: Feature Tests & CLI Verification
- Update `tests/Feature/Commands/AdminInstallCommandTest.php` to verify that `admin:install` executes without regressions and coordinates `anousss007/blatui` requirement.
- Verify existing command options (`--dry-run`, `--force`, `--fresh`, `--composer-path`, etc.) remain fully functional.

### Phase 4: ADR & Documentation
- Author `docs/decisions/ADR-011-direct-blatui-dependency-in-host-application.md` documenting:
  - Context & Rationale ("只负责安装" / 解除代持与 Boost AI 发现枷锁).
  - Decision: Detection via `hasDirectComposerPackage` and installation via `composer require ... -W`.
  - Consequences & Invariants.
- Update `docs/decisions/README.md` and `AGENTS.md` to reference ADR-011.

### Phase 5: Quality Gate Verification
- Run Pint (`composer lint:check` / `composer lint`).
- Run Larastan (`composer analyse`).
- Run Type Coverage (`composer test:types`).
- Run Unit & Feature tests (`composer test:unit`).
- Run Full CI suite (`composer test`).
