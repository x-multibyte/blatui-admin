> **Status: COMPLETE**
> **Authority:** This document defines the implementation specification for Phase 1 (Package Dependencies & Composer Alignment) and Phase 2 (Front-end Asset Build & Distribution Pipeline) in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md` and user directives.

# BlatUI Core Integration & Precompiled Asset Pipeline — Implementation Specification

## 1. Objective

Integrate core BlatUI foundation packages and transition `blatui-admin` from third-party runtime CDN dependencies (`jsdelivr` for `@tailwindcss/browser@4` and `alpinejs@3`) to a standalone, production-ready, precompiled asset build and distribution pipeline (`dist/admin.css` and `dist/admin.js`).

Per explicit user directive:
> "先focus 做好 第一&第二. 之后再另开分支做后续其他工作"
> (Focus strictly on Phase 1: Package Dependencies & Composer Alignment and Phase 2: Front-end Asset Build & Distribution Pipeline. Component migration to `admin::` namespace is deferred to a future branch).

### 1.1 Non-Goals & Rejected Alternatives (Do Not Reinvent)

- **Deferred to Next Branch**:
  - Full component migration and view refactoring to `admin::` namespace is deferred to a subsequent branch. Existing components in `resources/views/components/ui/` remain functional and gain access to `twMerge()` and Lucide icons without breaking current views.
- **Rejected: Coupling Host Applications to `laravel-vite-plugin` + `@vite`**:
  - Requiring host Laravel applications to import and compile package asset sources in their host `vite.config.js` is explicitly rejected. An admin package must be self-contained and drop-in. Forcing host apps to configure Node/Vite build pipelines for package internals destroys zero-configuration onboarding and introduces brittle dependency coupling.
- **Rejected: Runtime JIT CDN in Production**:
  - Relying on `@tailwindcss/browser@4` and unbundled Alpine CDN scripts from `cdn.jsdelivr.net` is rejected. Runtime CDNs introduce network latency, availability vulnerabilities, CSP friction, and complete failure in offline or air-gapped intranet environments.

### 1.2 Architecture Authority & Documentation Contract

Per `AGENTS.md` binding rules (*"An architecture decision change updates this file in the same commit"*):
- Introducing an internal asset build pipeline (`package.json`, Vite, `dist/`) and three runtime package dependencies constitutes an architecture-level decision.
- The implementation commit **must** update `AGENTS.md` in the same commit, adding an **"Asset Pipeline & Distribution Subsystem"** section that documents:
  1. Shipped precompiled distribution (`dist/admin.css` & `dist/admin.js`) tracked in git.
  2. Complete elimination of runtime CDN scripts.
  3. Single-writer dark mode contract owned by BlatUI's `themeStore` (`$store.theme.toggle()`, `localStorage('theme:mode')`).
  4. Symmetric asset lifecycle across `AdminServiceProvider`, `PublishCommand`, `UninstallCommand`, and `InstallCommand`.
  5. The recorded rejection of host-app Vite coupling.

---

## 2. Current State (Evidenced)

### 2.1 Dependencies in `composer.json:19-22`
```json
    "require": {
        "php": "^8.4",
        "illuminate/support": "^12.0||^13.0"
    },
```
- Missing upstream `anousss007/blatui` foundation library.
- Missing upstream peer dependencies required by BlatUI components (`gehrisandro/tailwind-merge-laravel`, `mallardduck/blade-lucide-icons`).

### 2.2 External CDN Dependencies in Views
Grep verification of CDN references in `resources/views`:
```bash
grep -rn "jsdelivr\|cdn\.min\.js\|tailwindcss" resources/views
# Output:
# resources/views/auth/login.blade.php:11:    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
# resources/views/auth/login.blade.php:14:    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
# resources/views/layouts/app.blade.php:27:    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
# resources/views/layouts/app.blade.php:30:    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```
- Both `resources/views/layouts/app.blade.php` and `resources/views/auth/login.blade.php` rely on runtime compilation via `@tailwindcss/browser@4` and unbundled Alpine CDN scripts.
- Vulnerable to network latency, CDN outages, Content Security Policy (CSP) violations, and offline/air-gapped environment failures.

### 2.3 Publish Tags in `src/AdminServiceProvider.php:62-85`
```php
        $this->publishes([
            __DIR__.'/../config/blatui-admin.php' => config_path('blatui-admin.php'),
        ], ['blatui-admin', 'blatui-admin-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/blatui-admin'),
        ], ['blatui-admin', 'blatui-admin-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/blatui-admin'),
        ], ['blatui-admin', 'blatui-admin-lang']);

        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], ['blatui-admin', 'blatui-admin-seeders']);

        $this->publishes([
            __DIR__.'/../routes/blatui-admin.php' => base_path('routes/admin.php'),
        ], ['blatui-admin', 'blatui-admin-routes']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['blatui-admin', 'blatui-admin-migrations']);
```
- Tag `blatui-admin-assets` is **not registered**.
- No `dist` folder exists in the package root.

### 2.4 Command State
- `src/Console/Commands/PublishCommand.php:26,56,76` already defines the `--assets` option and `'assets' => 'blatui-admin-assets'` mapping, but executing it publishes nothing because `AdminServiceProvider` lacks the tag.
- `src/Console/Commands/UninstallCommand.php:84` already contains `public_path('vendor/blatui-admin')` in `$directoriesToDelete`.
- `src/Console/Commands/InstallCommand.php:45-46` publishes config and routes, but does not publish assets upon installation.

### 2.5 Test Discrepancy in `tests/Feature/PublishTest.php:7-16`
```php
test('all 8 blatui-admin publish tags are properly registered', function () {
    $expectedTags = [
        'blatui-admin',
        'blatui-admin-config',
        'blatui-admin-migrations',
        'blatui-admin-seeders',
        'blatui-admin-routes',
        'blatui-admin-views',
        'blatui-admin-lang',
    ];
```
- The test title proclaims "all 8 blatui-admin publish tags", but the array only contains 7 tags. The missing 8th tag is `blatui-admin-assets`.

---

## 3. Target State

```
blatui-admin/
├── dist/                                  # Shipped precompiled assets (tracked in git)
│   ├── admin.css                          # Compiled Tailwind CSS v4 + BlatUI theme tokens
│   └── admin.js                           # Compiled Alpine.js + plugins + BlatUI core engine
├── resources/
│   ├── css/
│   │   └── admin.css                      # Tailwind v4 entrypoint & theme definitions
│   ├── js/
│   │   └── admin.js                       # Alpine bootstrap & BlatUI registration
│   └── views/
│       ├── layouts/app.blade.php          # Links to vendor/blatui-admin/admin.{css,js}
│       └── auth/login.blade.php           # Links to vendor/blatui-admin/admin.{css,js}
├── package.json                           # Internal package build dependencies (Vite + Tailwind)
├── vite.config.js                         # Asset compilation pipeline
└── composer.json                          # Phase 1 dependencies aligned
```

### 3.1 Phase 1: Package Dependencies & Composer Alignment

#### 3.1.1 `composer.json` Updates
Add the required BlatUI runtime dependencies into `require`:
```json
    "require": {
        "php": "^8.4",
        "anousss007/blatui": "^1.3",
        "gehrisandro/tailwind-merge-laravel": "^1.0",
        "illuminate/support": "^12.0||^13.0",
        "mallardduck/blade-lucide-icons": "^1.21||^2.0"
    },
```

#### 3.1.2 Testbench Service Providers in `tests/TestCase.php`
Ensure that `TestCase::getPackageProviders()` registers the service providers for Testbench execution:
```php
    protected function getPackageProviders($app): array
    {
        return [
            \BlatUI\Admin\AdminServiceProvider::class,
            \BlatUI\BlatuiServiceProvider::class,
            \TailwindMerge\Laravel\TailwindMergeServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \MallardDuck\LucideIcons\BladeLucideIconsServiceProvider::class,
        ];
    }
```

---

### 3.2 Phase 2: Front-end Asset Build & Distribution Pipeline

#### 3.2.1 Build Tooling: `package.json`
```json
{
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  },
  "devDependencies": {
    "@alpinejs/anchor": "^3.14.8",
    "@alpinejs/collapse": "^3.14.8",
    "@alpinejs/focus": "^3.14.8",
    "@floating-ui/dom": "^1.6.13",
    "@tailwindcss/vite": "^4.0.9",
    "alpinejs": "^3.14.8",
    "tailwindcss": "^4.0.9",
    "vite": "^6.2.0"
  }
}
```

#### 3.2.2 Build Tooling: `vite.config.js`
```javascript
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@blatui': path.resolve(dirname, 'vendor/anousss007/blatui/stubs/foundations'),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        admin: path.resolve(dirname, 'resources/js/admin.js'),
      },
      output: {
        entryFileNames: 'admin.js',
        // Never collide with a constant name: split chunks keep their own hashed names.
        chunkFileNames: 'admin-[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'admin.css';
          }
          return '[name][extname]';
        },
      },
    },
  },
});
```

> `__dirname` is undefined under `"type": "module"`; derive it from `import.meta.url`.
> `chunkFileNames` must keep `[hash]` — a constant `admin.js` makes every chunk overwrite the entry.

> `package-lock.json` is committed so downstream rebuilds and the CI freshness check (§6 step 6) are reproducible.

#### 3.2.3 Source Styles: `resources/css/admin.css`
`admin.css` is a **verbatim copy** of the upstream `vendor/anousss007/blatui/stubs/foundations/app.css`, with only the `@source` paths rewritten to this package's layout (`../views`, `../../src`, `../../vendor/...`). Do **not** hand-trim the `@theme inline` tokens or the `[data-base]` / `[data-theme]` / `[data-font]` / `[data-shadow]` / `[data-spacing]` preset blocks:

- `themeStore` writes those `data-*` attributes on `<html>`; without the CSS, preset switching is silently dead.
- The `--tracking-*` and `--shadow-*` scales are live utility drivers; trimming them breaks every `tracking-*` / `shadow-*` class.

The copy must preserve, from the upstream file:
- `@import 'tailwindcss';`
- rewritten `@source` lines for views, `src/`, and lucide SVGs
- `@custom-variant dark (&:is(.dark *));`
- the full `@theme inline` token mapping
- the complete `:root` / `.dark` token sets
- every `[data-*]` preset override block
- `[x-cloak]` and any Tailwind plugin directives the upstream ships

```css
@import "tailwindcss";

@source "../views";
@source "../../src";
@source "../../vendor/mallardduck/blade-lucide-icons/resources/svg/*.svg";

@custom-variant dark (&:is(.dark *));

/* ... remainder copied verbatim from stubs/foundations/app.css ... */
```

#### 3.2.4 Source Scripts: `resources/js/admin.js`
Mirrors the upstream greenfield bootstrap (`stubs/foundations/app.js`), with two package-level adjustments: (1) `registerBlatUI` receives `{ darkMode: 'system' }` so the theme store owns the `.dark` class and follows the OS preference by default; (2) the layout's own `darkMode` x-data is removed (§3.2.9) so only the store writes `.dark`.

```javascript
import '../css/admin.css';
import Alpine from 'alpinejs';
import { registerBlatUI } from '@blatui/blatui-core.js';

// registerBlatUI already registers the anchor/focus/collapse plugins and the
// theme store; calling alpine.plugin(...) for them here would double-register.
document.addEventListener('alpine:init', () => {
    registerBlatUI(window.Alpine, { darkMode: 'system' });
});

if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
```

#### 3.2.5 Distribution Registration: `src/AdminServiceProvider.php`
Register the publish tag in `boot()`:
```php
        $this->publishes([
            __DIR__.'/../dist' => public_path('vendor/blatui-admin'),
        ], ['blatui-admin', 'blatui-admin-assets']);
```

#### 3.2.6 Installation Automation: `src/Console/Commands/InstallCommand.php`
Add asset publishing into `handle()` step 3:
```php
        // 3. Publish config, routes, and assets
        $this->callSilent('vendor:publish', ['--tag' => 'blatui-admin-config']);
        $this->callSilent('vendor:publish', ['--tag' => 'blatui-admin-routes']);
        $this->callSilent('vendor:publish', ['--tag' => 'blatui-admin-assets']);
```

#### 3.2.7 View Updates (Eliminating CDN)

##### `resources/views/layouts/app.blade.php:26-30`
Replace:
```blade
    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```
With:
```blade
    <!-- BlatUI Admin Styles -->
    <link rel="stylesheet" href="{{ asset('vendor/blatui-admin/admin.css') }}">

    <!-- BlatUI Admin Scripts -->
    <script defer src="{{ asset('vendor/blatui-admin/admin.js') }}"></script>
```

同时移除 `x-data="{ ..., darkMode: ... }"`、`x-init="$watch('darkMode', ...)"` 与 `<html :class="{ 'dark': darkMode }">`（详见 §3.2.9）。

##### `resources/views/auth/login.blade.php:10-14`
Replace:
```blade
    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```
With:
```blade
    <!-- BlatUI Admin Styles -->
    <link rel="stylesheet" href="{{ asset('vendor/blatui-admin/admin.css') }}">

    <!-- BlatUI Admin Scripts -->
    <script defer src="{{ asset('vendor/blatui-admin/admin.js') }}"></script>
```

#### 3.2.8 Workbench Asset Publishing: `testbench.yaml`
Add `blatui-admin-assets` to `workbench.assets`:
```yaml
workbench:
  build:
    - asset-publish
    - create-sqlite-db
    - db-wipe
    - migrate-fresh
  assets:
    - laravel-assets
    - blatui-admin-assets
```

#### 3.2.9 Dark Mode Migration to `themeStore`
删除 layout 自带的双写入者，暗色状态只由 `themeStore`（`$store.theme`）写入：

- `resources/views/layouts/app.blade.php`：移除 `x-data` 中的 `darkMode` 字段、`x-init` 的 `$watch` 与 `<html :class="{ 'dark': darkMode }">` 绑定。`<html>` 不再通过 Alpine 绑定 `.dark`；`.dark` 完全由 `themeStore.apply()` 切换。
- `resources/views/partials/header.blade.php`：切换按钮改为调用 `$store.theme.toggle()`；图标 `x-show` 绑定改为 `$store.theme.isDark`（按现有 sun/moon 语义反转）。
- 持久化键从 `localStorage('theme')` 迁移到 `localStorage('theme:mode')`；老键可一次性清理。
- 默认策略 `darkMode: 'system'`：无显式选择时跟随 `prefers-color-scheme`，避免亮色应用被翻转。

```blade
<!-- header.blade.php -->
<button type="button" @click="$store.theme.toggle()" ...>
    <svg x-show="!$store.theme.isDark" ...>...</svg>
    <svg x-show="$store.theme.isDark" style="display: none;" ...>...</svg>
</button>
```

---

## 4. Behavioural Impact

### 4.1 Preserved
- All existing Blade templates, DOM structure, CSS class names, and Alpine interactions (`x-data`, `x-show`, `x-model`, `@click`, etc.) continue functioning identically.
- `Admin::url()`, `Admin::title()`, routes, models, and authentication workflows remain untouched.
- Full symmetry of `admin:publish` and `admin:uninstall`.

### 4.2 Changed
- **Zero CDN Dependencies**: No outbound requests to `cdn.jsdelivr.net`. The admin panel is fully operational offline and in secure air-gapped intranet environments.
- **Production Asset Delivery**: Precompiled CSS and JS are loaded via standard `<link rel="stylesheet">` and `<script defer>` tags from `public/vendor/blatui-admin/`.
- **Precompiled Bundles**: Shipped in `dist/` and committed to the repository so downstream package users require zero Node.js/npm dependencies.
- **Dark Mode Owned by `themeStore`**: `<html>` 的 `.dark` class 只由 `themeStore.apply()` 切换（策略 `darkMode: 'system'`），layout 的 `darkMode` x-data 双写入者已移除。持久化键为 `localStorage('theme:mode')`。

---

## 5. Test Plan

### 5.1 Tests to Invert / Update

#### `tests/Feature/PublishTest.php`
- **Current assertion**:
  ```php
  $expectedTags = [
      'blatui-admin',
      'blatui-admin-config',
      'blatui-admin-migrations',
      'blatui-admin-seeders',
      'blatui-admin-routes',
      'blatui-admin-views',
      'blatui-admin-lang',
  ];
  ```
- **Replacement assertion**:
  Include `'blatui-admin-assets'` in `$expectedTags`, genuinely satisfying the test title "all 8 blatui-admin publish tags are properly registered".

#### `tests/Feature/PublishCommandTest.php`
- **Current `cleanUpPublishedResources()`**: Missing cleanup of `public_path('vendor/blatui-admin')`.
- **Replacement**: Add `File::deleteDirectory(public_path('vendor/blatui-admin'));` in `cleanUpPublishedResources()`.
- **`it publishes all resources when using the --all option`**: Add:
  ```php
  expect(File::isDirectory(public_path('vendor/blatui-admin')))->toBeTrue();
  expect(File::exists(public_path('vendor/blatui-admin/admin.css')))->toBeTrue();
  expect(File::exists(public_path('vendor/blatui-admin/admin.js')))->toBeTrue();
  ```

#### `tests/Feature/UninstallCommandTest.php`
- **In `test('TC-4 it deletes published resources including routes and seeders symmetrically')`**:
  Set up `File::ensureDirectoryExists(public_path('vendor/blatui-admin')); File::put(public_path('vendor/blatui-admin/admin.css'), '/* css */');`
  Assert after uninstall:
  ```php
  expect(File::isDirectory(public_path('vendor/blatui-admin')))->toBeFalse();
  ```

### 5.2 Tests to Add

#### `tests/Feature/PublishCommandTest.php`
- **`test('TC-6 it publishes assets into public directory byte for byte')`**:
  ```php
  test('TC-6 it publishes assets into public directory byte for byte', function () {
      $this->artisan('admin:publish', [
          '--assets' => true,
          '--force' => true,
      ])->assertSuccessful();

      expect(File::isDirectory(public_path('vendor/blatui-admin')))->toBeTrue();
      expect(File::exists(public_path('vendor/blatui-admin/admin.css')))->toBeTrue();
      expect(File::exists(public_path('vendor/blatui-admin/admin.js')))->toBeTrue();
      expect(File::get(public_path('vendor/blatui-admin/admin.css')))
          ->toBe(File::get(packageSourcePath('dist/admin.css')));
      expect(File::get(public_path('vendor/blatui-admin/admin.js')))
          ->toBe(File::get(packageSourcePath('dist/admin.js')));
  });
  ```

#### `tests/Feature/InstallCommandTest.php`
- **`test('admin:install publishes assets along with config and routes')`**:
  Assert that after running `admin:install`, `public_path('vendor/blatui-admin/admin.css')` exists.

#### `tests/Feature/AdminLayoutTest.php`
- **`test('layout renders compiled assets and eliminates external CDNs')`**:
  Assert:
  ```php
  $response->assertSee('vendor/blatui-admin/admin.css');
  $response->assertSee('vendor/blatui-admin/admin.js');
  $response->assertDontSee('cdn.jsdelivr.net');
  ```

#### `tests/Feature/LoginDashboardViewTest.php`
- **`test('login view renders compiled assets and eliminates external CDNs')`**:
  Assert that `GET /admin/auth/login`:
  ```php
  $response->assertSee('vendor/blatui-admin/admin.css');
  $response->assertSee('vendor/blatui-admin/admin.js');
  $response->assertDontSee('cdn.jsdelivr.net');
  ```

---

## 6. Verification Steps & Commands

1. **Asset Build Verification**:
   ```bash
   npm install
   npm run build
   # Verify output files exist and are non-empty
   test -s dist/admin.css && test -s dist/admin.js
   ```

2. **CDN Elimination Check**:
   ```bash
   grep -rn "jsdelivr\|cdn\.min\.js" resources/views
   # Expected output: EMPTY (0 results)
   ```

3. **Blade Modernization Escaping Invariant**:
   ```bash
   grep -rn '{!!' resources/views
   # Expected output: EMPTY (0 results, per AGENTS.md contract)
   ```

4. **Workbench Build & Serve**:
   ```bash
   composer build
   # Verify workbench has copied assets
   test -s workbench/public/vendor/blatui-admin/admin.css
   ```

5. **Full Test Suite & Static Analysis**:
   ```bash
   composer test
   # Passes: phpstan (0 errors), pint (clean), pest (100% type coverage, 0 failures)
   ```

6. **Asset Freshness Check (CI)**:
   ```bash
   npm ci && npm run build
   git diff --exit-code dist/
   # Expected: no diff — committed dist/ matches the source + lockfile build
   ```

7. **Dark Mode Single-Writer Check**:
   ```bash
   grep -rn "localStorage.getItem('theme')\|localStorage.setItem('theme'" resources/views
   # Expected: EMPTY (0 results — only theme:mode may appear)
   ```

---

## 7. Risks & Mitigation

| Risk | Impact | Mitigation Strategy |
| :--- | :--- | :--- |
| Downstream app doesn't have Node/npm | Asset compilation failure | Precompiled assets in `dist/` are tracked in git and distributed via Composer. Downstream users only run `vendor:publish`. |
| Tailwind v4 utility purging misses dynamic classes in PHP | Styles missing on badges/alerts | `@source "../../src"` scans PHP classes (e.g. `src/Grid/Displayers/Badge.php`). |
| Conflict with host application's Alpine instance | Duplicate stores/plugins error | `registerBlatUI` is wired through `alpine:init`, which Alpine guarantees fires exactly once before start; a Livewire-provided `window.Alpine` is adopted via the `window.Alpine` check, mirroring the upstream bootstrap. |
| Downstream user upgrades package but old assets cached | Outdated CSS/JS in public directory | Users re-publish with `--force`. Documented in `admin:publish` guide. |
