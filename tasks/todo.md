# Tasks: Direct BlatUI Host Dependency Installation

- [x] Task 1: Add `hasDirectComposerPackage` and update `checkBlatUI` in `EnvironmentDetector`
  - Acceptance: `hasDirectComposerPackage` returns `true` only if package is in target `composer.json` (`require` or `require-dev`); returns `false` if only in `composer.lock` or runner runtime.
  - Verify: `vendor/bin/pest tests/Unit/Installer/Diagnostics/EnvironmentDetectorTest.php`
  - Files:
    - `src/Console/Installer/Diagnostics/EnvironmentDetector.php`
    - `tests/Unit/Installer/Diagnostics/EnvironmentDetectorTest.php`

- [x] Task 2: Update `InstallWizard::requiredComposerPackages` to enforce direct dependency requirement
  - Acceptance: `requiredComposerPackages` yields `anousss007/blatui:^1.36` whenever `hasDirectComposerPackage('anousss007/blatui')` is false.
  - Verify: `vendor/bin/pest tests/Unit/Installer/Wizard/InstallWizardTest.php`
  - Files:
    - `src/Console/Installer/Wizard/InstallWizard.php`
    - `tests/Unit/Installer/Wizard/InstallWizardTest.php`

- [x] Task 3: Feature tests for `admin:install` command execution
  - Acceptance: `admin:install` correctly coordinates options and triggers installation pipeline including `anousss007/blatui`.
  - Verify: `vendor/bin/pest tests/Feature/Commands/AdminInstallCommandTest.php`
  - Files:
    - `tests/Feature/Commands/AdminInstallCommandTest.php`

- [x] Task 4: Author ADR-011 and update documentation
  - Acceptance: `ADR-011-direct-blatui-dependency-in-host-application.md` documents motivation ("只负责安装" / 解除代持枷锁 / 解决 Boost 发现问题), and is indexed in `docs/decisions/README.md` and `AGENTS.md`.
  - Verify: File exists and passes markdown check.
  - Files:
    - `docs/decisions/ADR-011-direct-blatui-dependency-in-host-application.md`
    - `docs/decisions/README.md`
    - `AGENTS.md`
    - `SPEC.md`

- [x] Task 5: Run Full Quality Gate Pipeline
  - Acceptance: 100% Type Coverage, zero PHPStan issues, Pint compliant, all Pest tests pass.
  - Verify: `composer test`
  - Files:
    - Entire project
