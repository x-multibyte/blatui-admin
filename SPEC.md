# Spec: BlatUI Admin Lifecycle & Dependency Management Engine

## 1. Objective

`x-multibyte/blatui-admin` is a Laravel package built on **BlatUI (`anousss007/blatui`)**.
The primary responsibility of this package is to serve as the **lifecycle and dependency management engine** for BlatUI and its administrative architecture within Laravel host applications.

It orchestrates and resolves the cross-stack dependency chain required by BlatUI:

- **PHP / Laravel Dependency Detection & Installation**: On-demand detection and integration of peer packages including Livewire, Blade Lucide Icons, and Tailwind-Merge.
- **Node.js / Frontend Toolchain Adaptation**: Node 18+ runtime verification, automatic package manager detection (`npm`, `pnpm`, `yarn`, `bun`, `npx`), and installation of Alpine.js plugins and optional charting packages (ApexCharts).
- **Existing Project Integration**: Additive, non-destructive wiring of BlatUI styles and runtimes into the host application's `resources/css/app.css` and `resources/js/app.js`, **strictly eliminating duplicate-Alpine collisions and theme conflicts**.
- **File & Component Provisioning**: On-demand provisioning of core administrative Blade components into `resources/views/components/ui/` via `blatui:add`, alongside configuration and database migrations.

Target users are developers integrating BlatUI Admin into either greenfield or **existing** modern Laravel applications.

---

## 2. Core Principles: Adaptive & Non-Destructive Integration

Because the package is installed into a host application, parts or all of the required dependencies may already exist. The installer strictly adheres to the following adaptation matrix:

### 2.1 CSS / Tailwind Adaptation Rules

- **Tailwind v4+**: Never overwrite the host application's `app.css`. Use **additive insertion**: inject `@import "./blatui.css";` after existing imports and before user-defined CSS rules.
- **Tailwind v3**: Flag a `WARN` in the pre-flight diagnostic report with official migration instructions (`npx @tailwindcss/upgrade`). Do not force mutations on incompatible stylesheets.

### 2.2 JS / Alpine / Livewire Runtime Adaptation Rules (Zero Duplicate-Alpine)

- **Greenfield Projects (No Alpine, No Livewire)**:
  - Install core runtime and required plugins: `npm install -D alpinejs @alpinejs/anchor @alpinejs/collapse @alpinejs/focus`.
  - Inject bootstrap import into `app.js`: `import "./blatui.js";`.
- **Existing Livewire or Flux Projects**:
  - **Strictly prohibit installing the `alpinejs` npm package** (Livewire internally bundles Alpine; re-installing causes fatal duplicate-Alpine collisions).
  - Install required plugins only, omitting the `alpinejs` core package.
  - Inject the integration listener into `app.js` targeting the `alpine:init` lifecycle event:
    ```javascript
    import { registerBlatUI } from './blatui-core.js'

    document.addEventListener('alpine:init', () => {
        registerBlatUI(window.Alpine) // Pass { theme: false } if Flux is detected
    })
    ```
- **Existing Standalone Alpine (Without Livewire)**:
  - Do not import `blatui.js` (which invokes `Alpine.start()`). Import `blatui-core.js` and register plugins onto the existing `window.Alpine` instance.

### 2.3 Opt-in Charting Dependencies (ApexCharts)

- `apexcharts` is a heavy client-side dependency (~150KB minified). **It must never be enforced as a mandatory global dependency**.
- Only when opted in via interactive prompt or the `--install-apex-charts` CLI flag will the installer execute `npm install -D apexcharts` and publish `blatui-charts` resources.

### 2.4 Component File Distribution (`blatui:add`)

- BlatUI adopts a code-ownership model, distributing components into `resources/views/components/ui/` via `php artisan blatui:add <components>`.
- The installer predefines the core component set required by the administrative interface (e.g., `button`, `card`, `input`, `dropdown`, `table`).
- If target components already exist in the host project, the installer skips them or requests user confirmation before overwriting, preventing accidental loss of custom code.

### 2.5 PHP Peer Dependency Decoupling & Adaptive Resolution

- `gehrisandro/tailwind-merge-laravel` and `mallardduck/blade-lucide-icons` are **peer packages** required by BlatUI Blade components distributed into the host project.
- To prevent SAT-solver transitive version lock collisions (such as Guzzle 7 vs Guzzle 8 in fresh Laravel 13 projects), they are decoupled from the package's root `require` and declared as `require-dev` / `suggest`.
- During `blatui:admin --install`, `EnvironmentDetector` checks for their presence. Any missing peer packages are installed using `composer require ... -W` (`--with-all-dependencies`), allowing Composer to automatically negotiate transitive version alignments.

---

## 3. Capability Map & Build Order

The dependency management lifecycle is partitioned into four decoupled tiers:

| Module ID | Responsibility | Depends On | Core Behavior |
| :--- | :--- | :--- | :--- |
| `diagnostics` | Pre-flight environment & dependency sniffing | — | Inspects host environment (PHP, Node, package managers, Livewire, Alpine, Flux, Tailwind, peer packages) and generates a structured `DiagnosticReport`. |
| `actions` | Headless execution toolkit | — | Encapsulates `ProcessRunner` (npm/pnpm/yarn/bun/npx/composer), adaptive `ViteWiringManager`, `StubManager`, and `ArtisanRunner`. |
| `wizard` | Prompts-based interactive orchestrator | `diagnostics`, `actions` | Renders diagnostic tables via `laravel/prompts`, collects installation preferences, and coordinates actions under `spin()` indicators. |
| `cli` | Console command entrypoints | `wizard` | Registers canonical command `php artisan blatui:admin --install` and provides `admin:install` compatibility proxy. |

**Build Order:** `diagnostics` → `actions` → `wizard` → `cli`

> Detailed architectural decisions and constraints are documented in [Architecture Decision Records (ADRs)](docs/decisions/README.md) (ADR-001 through ADR-008).

---

## 4. Tech Stack

- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0` (`illuminate/support`, `illuminate/console`, `illuminate/process`)
- **Core Package**: `anousss007/blatui` (`^1.36`)
- **CLI Interaction**: `laravel/prompts` (`^0.3 || ^0.4`)
- **Process Component**: Laravel `Process` Facade (wrapping `symfony/process`)
- **Testing**: Pest `^4.6 || ^5.0`, Orchestra Testbench `^10.0 || ^11.0`, Pest Type Coverage (`--min=100`)
- **Static Analysis**: PHPStan / Larastan (Level 7)
- **Code Style**: Laravel Pint (`preset: laravel`)

---

## 5. Commands

```bash
# Development & Testing
Build Assets:      composer build        # vendor/bin/testbench workbench:build --ansi
Serve Workbench:   composer serve        # vendor/bin/testbench serve --ansi
Run Tests:         composer test:unit    # vendor/bin/pest --parallel
Type Coverage:     composer test:types   # vendor/bin/pest --type-coverage --min=100
Code Style Check:  composer lint:check   # vendor/bin/pint --parallel --test
Code Style Fix:    composer lint         # vendor/bin/pint --parallel
Static Analysis:   composer analyse      # vendor/bin/phpstan analyse
Full CI Pipeline:  composer test         # analyse + lint:check + test:types + test:unit

# Package Console Commands
Primary Install:   php artisan blatui:admin --install
Dry Run Plan:      php artisan blatui:admin --install --dry-run
Force Reinstall:   php artisan blatui:admin --install --force
Fresh Migration:   php artisan blatui:admin --install --fresh
Legacy Alias:      php artisan admin:install [--dry-run] [--force] [--fresh]
```

---

## 6. Project Structure

```
src/
├── Admin.php                              # Core Admin manager singleton
├── AdminServiceProvider.php               # Service provider (bindings, commands, publishes, routes)
├── Facades/
│   └── Admin.php                          # Admin Facade
├── Console/
│   ├── Commands/
│   │   ├── AdminCommand.php               # Informational / placeholder command
│   │   ├── AdminInstallCommand.php        # Backwards-compatible proxy command
│   │   └── BlatuiAdminCommand.php         # Canonical entrypoint: blatui:admin {--install}
│   └── Installer/
│       ├── Diagnostics/                   # Read-only environmental checks
│       │   ├── CheckStatus.php            # PASS | WARN | FAIL status enum
│       │   ├── CheckResult.php            # Immutable check result DTO
│       │   ├── DiagnosticReport.php       # Diagnostic report aggregate
│       │   └── EnvironmentDetector.php    # Host environment sniffer
│       ├── Actions/                       # Headless execution primitives
│       │   ├── ProcessRunner.php          # npm/pnpm/yarn/bun/npx/composer process wrapper
│       │   ├── ViteWiringManager.php      # Adaptive, non-destructive app.css and app.js wiring
│       │   ├── StubManager.php            # Component existence verification and directory setup
│       │   └── ArtisanRunner.php          # Safe Artisan dispatcher (publish, migrate, blatui:add)
│       └── Wizard/                        # Prompts-based orchestrator
│           ├── InstallContext.php         # Immutable runtime options DTO
│           ├── StepResult.php             # Step execution outcome DTO
│           └── InstallWizard.php          # Interactive workflow coordinator
tests/
├── ArchTest.php                           # Architecture & security invariants (strict_types, no env/dd)
├── TestCase.php                           # Testbench base test case
├── Unit/
│   └── Installer/
│       ├── Diagnostics/                   # Environment detection unit tests
│       ├── Actions/                       # Actions execution unit tests
│       └── Wizard/                        # Wizard orchestration unit tests
└── Feature/
    └── Commands/                          # Artisan console command feature tests
```

---

## 7. Code Style & Standards

1. **Strict Types**: Every PHP file MUST declare `declare(strict_types=1);`.
2. **Type Safety**: All class properties, method parameters, and return types must be explicitly typed, maintaining 100% type coverage.
3. **Additive Operations**: File mutations must be additive. Never overwrite existing user code without confirmation.
4. **Pure Prompts UI**: Console interactions and output must exclusively use `laravel/prompts`, eliminating mixed usage with `Command::$components`.

---

## 8. Boundaries

### Always Do

- In hosts with existing Livewire installations, **strictly avoid** installing the `alpinejs` npm package.
- Inspect `app.css` and `app.js` prior to insertion to guarantee idempotent operations.
- On blocking environmental failures (e.g. Node.js missing or version < 18), output an actionable `suggestedFix` and abort safely.
- Support `--dry-run` across all commands, providing full diagnostic reporting and planned action summaries.

### Ask First

- Overwriting existing user components in `resources/views/components/ui/`.
- Modifying parameters of the backwards-compatible `admin:install` command.
- Installing optional heavy dependencies (e.g. `apexcharts`).

### Never Do

- Directly overwrite or replace the host application's `resources/css/app.css` or `resources/js/app.js`.
- Leave debugging statements (`dd()`, `ddd()`, `dump()`, `exit()`) in production code.
- Call `env()` directly within `src/` (strictly enforced via `ArchTest`).
- Force a standalone Alpine runtime when Livewire or Flux is already present.

---

## 9. Success Criteria

1. [x] Executing `php artisan blatui:admin --install --dry-run` accurately inspects host dependencies and outputs diagnostic tables and action plans with exit code 0.
2. [x] Executing `php artisan admin:install --dry-run` operates identically as a backwards-compatible alias.
3. [x] Pre-flight diagnostics correctly classify diverse host environments:
    - Existing Livewire -> avoids installing npm `alpinejs`
    - Existing Tailwind v4 -> executes additive CSS injection
    - Existing Tailwind v3 -> outputs upgrade guidance warning
    - Accurately detects present lockfiles (`bun`, `pnpm`, `yarn`, `npm`)
4. [x] Legacy dead code (`InstallStep.php`, `InteractsWithEnvironment.php`, monolithic step classes) completely purged.
5. [x] Passes all four quality gates via `composer test` (Pint styling, PHPStan Level 7 with zero errors, 100% Type Coverage, Pest unit tests green).
