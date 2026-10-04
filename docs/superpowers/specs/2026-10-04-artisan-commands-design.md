> **Status: COMPLETE (verified 2026-10-04)**
> **Authority:** This document defines the implementation specification for the Artisan command surface in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md`.

# Artisan 命令集 — Implementation Specification

## 1. Objective

Replace the placeholder `blatui-admin:placeholder` command with a real, self-documenting command surface: a banner command, a command lister, a guided resource publisher, and an uninstaller.

The package shipped `blatui-admin:placeholder`, which printed `Admin placeholder command executed.` It advertised nothing, published nothing on its own, and could not be removed. Users who published resources with raw `vendor:publish --tag=...` had to memorize tags; removing the package required hand-deleting config, routes, views, lang, assets, and migration files.

This specification closes that gap with four commands and one shared trait.

## 2. Current State (Evidenced)

1. **Placeholder only**: `src/Console/Commands/AdminCommand.php` declared `$signature = 'blatui-admin:placeholder'` with a single hardcoded `line()` call.
2. **Publish tags existed but were undiscoverable**: `src/AdminServiceProvider.php` already registered seven tags — `blatui-admin`, `-config`, `-views`, `-lang`, `-assets`, `-seeders`, `-routes`, `-migrations` — but only through raw `vendor:publish` in the README.
3. **Published paths**: config → `config/blatui-admin.php`; routes → `routes/admin.php`; views → `resources/views/vendor/blatui-admin`; lang → `lang/vendor/blatui-admin`; assets → `public/vendor/blatui-admin`; migrations → `database/migrations`.
4. **No version reader**: nothing in `src/` could report the installed package version.
5. **`InstallCommand` was registered but out of scope here** — this specification does not alter it.

## 3. Target Architecture

Four commands plus a shared trait, all registered in `AdminServiceProvider::registerCommands()`.

| Command | Signature | Responsibility |
| :--- | :--- | :--- |
| `AdminCommand` | `admin` | Banner + version + discovered command list |
| `ListCommand` | `admin:list` | Same banner output, no side effects |
| `PublishCommand` | `admin:publish` | Interactive or flag-driven resource publishing |
| `UninstallCommand` | `admin:uninstall` | Roll back migrations, delete published files |

### 3.1 `PrintsAdminInfo` trait — `src/Console/Commands/Concerns/PrintsAdminInfo.php`

Shared banner logic, extracted because both `AdminCommand` and `ListCommand` render identical output. This is the "extension point is real" case: two consumers, one implementation.

- `printAdminInfo()` — ASCII banner, `BlatUI Admin v{version}`, then delegates to `printCommandList()`.
- `printCommandList()` — enumerates `$this->getApplication()->all('admin')`, filters out `admin` itself, sorts with `ksort()` for stable output, and pads names to the longest command name so descriptions align.

Console `<fg=...>` tags are the console formatter's own markup, not Blade output. The rendering contract in `AGENTS.md` governs `resources/views/`; console coloring is Symfony Console's native mechanism and is out of its scope.

### 3.2 `Admin::version()` — `src/Admin.php`

Reads `Composer\InstalledVersions::getPrettyVersion('x-multibyte/blatui-admin')`, guarded by `isInstalled()`. Returns `'dev'` when the package is not present in Composer's runtime registry (path repository, source install) so the banner never renders an empty version.

### 3.3 `PublishCommand`

Signature: `admin:publish {--config} {--migrations} {--routes} {--seeders} {--views} {--lang} {--assets} {--a|all} {--f|force}`.

- `--all` maps to the aggregate `blatui-admin` tag.
- Individual flags map to their matching tags.
- With no flags, an interactive `multiselect()` prompt offers all seven resource tags; an empty selection exits successfully without publishing.
- Unless `--force`, a `confirm()` prompt asks about overwriting existing files.
- Publishing delegates to `$this->call('vendor:publish', ['--tag' => $tag, '--force' => $force])` — it does not reimplement file copying, so the service provider stays the single source of truth for publish paths.

Every tag the service provider registers is reachable through a flag. There is no resource the package can publish only by memorising a tag string.

### 3.4 `UninstallCommand`

Signature: `admin:uninstall {--f|force}`.

Order of operations:

1. Without `--force`, a `warning()` states the blast radius and a `confirm()` gates the run. Declining returns `SUCCESS` with `Uninstall cancelled.` — declining is not an error.
2. Roll back migrations via `Artisan::call('migrate:rollback', ['--path' => 'vendor/x-multibyte/blatui-admin/database/migrations', '--force' => true])`.
3. Delete `config/blatui-admin.php` and `routes/admin.php`.
4. Delete the `views/vendor/blatui-admin`, `lang/vendor/blatui-admin`, and `public/vendor/blatui-admin` directories.
5. Delete published `*_create_admin_tables.php` migrations from `database/migrations/`.
6. Delete the published `database/seeders/AdminTablesSeeder.php`.

Publish and uninstall are symmetric: everything `admin:publish` can write, `admin:uninstall` removes.

Application code under `App\Admin\` is never touched — the package does not own it.

The migration rollback failure is caught and reported with `$this->error()` rather than aborting, so a rollback failure still lets the user clean up their published files. This is the one place an exception is swallowed, and it is deliberate: the remaining cleanup steps are independent of the rollback, and aborting would strand the user's published files.

### 3.5 Placeholder removal

`blatui-admin:placeholder` no longer exists. `AdminCommand` takes the bare `admin` signature. This is a breaking change to a placeholder that shipped no useful behavior.

## 4. Tests

Behavioral tests through the public Artisan surface, per `AGENTS.md`'s "tests focused on observable package behavior through public APIs, service provider wiring, commands."

| File | Covers |
| :--- | :--- |
| `tests/Feature/ExampleTest.php` | `admin` and `admin:list` are registered and print the banner |
| `tests/Feature/PublishCommandTest.php` | `--config`, `--routes`, `--seeders`, `--all`, interactive prompt, empty selection |
| `tests/Feature/UninstallCommandTest.php` | Declined confirmation, confirmed deletion of every published artifact, migration rollback + deletion, seeder deletion, `--force` bypasses the prompt |

Filesystem assertions use `config_path()`, `base_path()`, `resource_path()`, `lang_path()`, `public_path()`, and `database_path()` against the Testbench skeleton, with `File::delete()` cleanup in `beforeEach`/`afterEach`.

**Defect corrected during this work:** the two command test files were initially written with their contents transposed — `PublishCommandTest.php` held the uninstall tests and `UninstallCommandTest.php` held the publish tests. The suite passed because each file's content was self-consistent, but the filenames asserted the opposite of their contents. Contents were moved to their matching filenames, and the shared `cleanPublishedFiles()` helper was renamed per file so the two definitions cannot collide as global symbols.

## 5. Documentation

- `README.md` gains an "Artisan Commands" section documenting all four commands.
- `AGENTS.md` gains an "Artisan Commands" section recording the command surface, the trait's existence, and the §6 rejections.

## 6. Rejected

- **A `--no-interaction` flag.** Laravel Prompts already falls back to non-interactive behavior through Artisan's standard `--no-interaction`. A bespoke flag would duplicate it.
- **Deleting `App\Admin\` application files on uninstall.** The package does not own application code; deleting it would destroy user work.
- **Rendering the command list by reading a static array.** The list is discovered from `$app->all('admin')`, so a command registered by a third party appears automatically. A hardcoded list would drift.

## 7. Revision — routes and seeders were an omission

The first implementation shipped `--config`, `--migrations`, `--views`, `--lang`, and `--assets` only, and recorded the absence of `--routes` and `--seeders` as a deliberate rejection on the grounds that `admin:uninstall` deletes `routes/admin.php`, so offering to publish it seemed inconsistent.

That reasoning was wrong. The asymmetry ran the wrong way: the service provider registers all seven tags, so `vendor:publish --tag=blatui-admin-routes` always worked — the omission did not protect the user from anything, it only forced them to memorise a tag string while every other resource had a flag. It was a gap in the flag list, not a design decision.

Both flags are now exposed, the interactive list offers all seven tags, and `admin:uninstall` additionally deletes the published `database/seeders/AdminTablesSeeder.php` so publish and uninstall are symmetric.

## 8. Verification

```
composer test    # phpstan 0 errors, pint clean, pest green, 100% type coverage
```

Plus the structural check the rendering contract requires:

```
grep -rn '{!!' resources/views    # no output
```
