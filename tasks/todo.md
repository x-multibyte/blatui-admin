# Task List: Decouple Composer Peer Dependencies to Resolve Laravel 13 Guzzle Conflict

- [x] **Task 1: Architectural Documentation (ADR-008 & SPEC Update)**
  - [x] 1.1 Create `docs/decisions/ADR-008-composer-peer-dependencies-decoupling.md`
  - [x] 1.2 Update `docs/decisions/README.md` to index ADR-008
  - [x] 1.3 Update `SPEC.md` to clarify peer package decoupling and `-W` installer behavior

- [x] **Task 2: Decouple Peer Dependencies in `composer.json`**
  - [x] 2.1 Move `tailwind-merge-laravel` & `blade-lucide-icons` from `"require"` to `"require-dev"`
  - [x] 2.2 Add `"suggest"` block for peer packages in `composer.json`
  - [x] 2.3 Run `composer update --no-interaction` to verify local lockfile and dependencies

- [ ] **Task 3: Support `--with-all-dependencies` (-W) in `ProcessRunner`**
  - [ ] 3.1 Update `ProcessRunner` with `$withAllDependencies` parameter
  - [ ] 3.2 Add unit tests for `-W` flag in `tests/Unit/Installer/Actions/ProcessRunnerTest.php`

- [ ] **Task 4: Integrate `-W` in `InstallWizard` Composer Step**
  - [ ] 4.1 Update `InstallWizard::stepInstallComposerDependencies` to pass `withAllDependencies: true`
  - [ ] 4.2 Update unit tests in `tests/Unit/Installer/Wizard/InstallWizardTest.php`

- [ ] **Task 5: Full Quality Gates & Host Verification**
  - [ ] 5.1 Run full CI pipeline (`composer test`: Pint, PHPStan, Type Coverage, Pest)
  - [ ] 5.2 Verify compatibility and clean rollback state
