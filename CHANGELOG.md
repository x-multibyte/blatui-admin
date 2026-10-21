# Release Notes

All notable changes to `x-multibyte/blatui-admin` will be documented in this file.

## v0.6.0 - 2026-10-22

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* feat(boost): integrate laravel boost AI guidelines, skills and archive distribution by @x-multibyte in https://github.com/x-multibyte/blatui-admin/commit/7f60858aea534093208e00d1d9296caaf561d564
  - **Distribution Packaging**: Remove `/resources/boost export-ignore` from `.gitattributes` to ensure Composer dist packages bundle Boost AI context assets.
  - **AI Context Integration**: Provide dual-tier guidance with core guidelines (`resources/boost/guidelines/core.blade.php`) and admin domain skill (`resources/boost/skills/blatui-admin-development/SKILL.md`).
  - **Installer Detection**: Automatically detect `laravel/boost` in host applications during `blatui:admin` installer runs and prompt developers to sync via `php artisan boost:update --discover`.
  - **Architecture Decision**: Documented in [ADR-010: Laravel Boost AI Context Integration & Distribution Strategy](docs/decisions/ADR-010-laravel-boost-ai-context-integration-and-distribution.md).

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.5.5...v0.6.0

## v0.5.5 - 2026-10-20

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Dependencies

* chore(deps): bump actions/checkout from 4 to 7 by @dependabot[bot] in https://github.com/x-multibyte/blatui-admin/pull/12

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/x-multibyte/blatui-admin/pull/12

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.5.4...v0.5.5

## v0.5.4 - 2026-10-20

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Maintenance

* ci(github): add Dependabot auto-merge workflow by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/17

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.5.3...v0.5.4

## v0.5.3 - 2026-10-20

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Maintenance

* ci(github): add automatic PR labeler workflow based on conventional commits by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/16

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.5.2...v0.5.3

## v0.5.2 - 2026-10-18

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* fix(installer): strengthen peer constraints, align remediation, and remove placeholder migration by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/15

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.5.1...v0.5.2

## v0.5.1 - 2026-10-18

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* fix(installer): align default admin component names with blatui registry and guard asset publish by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/14

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.5.0...v0.5.1

## v0.5.0 - 2026-10-18

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* feat(installer): decouple peer dependencies to support Laravel 13 by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/13

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.4.1...v0.5.0

## v0.4.1 - 2026-10-18

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* docs: establish ADRs and refresh project documentation in English by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/11

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.4.0...v0.4.1

## v0.4.0 - 2026-10-18

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* feat(installer): implement CLI command entrypoints and purge legacy code by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/10

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.3.0...v0.4.0

## Unreleased

### Added

- `blatui:admin`: Added primary command (`php artisan blatui:admin {--install}`) with support for `--dry-run`, `--fresh`, `--force`, `--composer-path=`, `--install-apex-charts`, and `--components=*`.

### Changed

- `admin:install`: Refactored from monolithic command into a lightweight backwards-compatible proxy delegating to `InstallWizard`.
- Migrations: The automated installer pipeline executes migrations with `--force` to support headless and non-interactive environments without hanging on production confirmation prompts (destructive table drops remain strictly guarded by `--fresh`).

## v0.3.0 - 2026-10-17

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* feat(installer): implement wizard orchestration engine by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/9

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.2.0...v0.3.0

## v0.2.0 - 2026-10-17

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* feat(installer): implement actions execution toolkit by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/8

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.1.0...v0.2.0

## v0.1.0 - 2026-10-17

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* feat(installer): implement diagnostics and environment detection system by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/7

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.0.2...v0.1.0

## v0.0.2 - 2026-10-17

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* chore: configure gitignore exclusions and update core dependencies by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/6

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/compare/v0.0.1...v0.0.2

## v0.0.1 - 2026-10-17

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* Update from task 728edbb3-739e-45a6-b6f4-c231cc269432 by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/1
* Update from task 728edbb3-739e-45a6-b6f4-c231cc269432 by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/2
* Update from task 31054346-c5c9-4e26-83dc-f6f121bc7e45 by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/3
* feat(install): install Livewire by default and optimize BlatUI setup pipeline by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/4
* feat(ci): add automated release workflow on PR merge starting at v0.0.1 by @x-multibyte in https://github.com/x-multibyte/blatui-admin/pull/5

### New Contributors

* @x-multibyte made their first contribution in https://github.com/x-multibyte/blatui-admin/pull/1

**Full Changelog**: https://github.com/x-multibyte/blatui-admin/commits/v0.0.1

## [Unreleased]
