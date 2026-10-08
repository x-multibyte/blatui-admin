> **Status: IN PROGRESS**
> **Authority:** This document defines the implementation specification for Phase 1 (Package Dependencies & Composer Alignment) and Phase 2 (Front-end Asset Build & Distribution Pipeline) in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md` and user directives.

# BlatUI Core Integration & Precompiled Asset Pipeline — Implementation Specification

## 1. Objective

Integrate core BlatUI foundation packages and transition `blatui-admin` from third-party runtime CDN dependencies (`jsdelivr` for `@tailwindcss/browser@4` and `alpinejs@3`) to a standalone, production-ready, precompiled asset build and distribution pipeline (`dist/admin.css` and `dist/admin.js`).

Per explicit user directive:
> "先focus 做好 第一&第二. 之后再另开分支做后续其他工作"
> (Focus strictly on Phase 1: Package Dependencies & Composer Alignment and Phase 2: Front-end Asset Build & Distribution Pipeline. Component migration to `admin::` namespace is deferred to a future branch).

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
import path from 'path';

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@blatui': path.resolve(__dirname, 'vendor/anousss007/blatui/stubs/foundations'),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        admin: path.resolve(__dirname, 'resources/js/admin.js'),
      },
      output: {
        entryFileNames: 'admin.js',
        chunkFileNames: 'admin.js',
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

#### 3.2.3 Source Styles: `resources/css/admin.css`
The stylesheet provides full Tailwind CSS v4 utility classes and BlatUI design tokens:
```css
@import "tailwindcss";

@source "../views";
@source "../../src";
@source "../../vendor/mallardduck/blade-lucide-icons/resources/svg/*.svg";

@custom-variant dark (&:is(.dark *));

/* -------------------------------------------------------------------------- */
/*  BlatUI Design Tokens & Theme Variables                                    */
/* -------------------------------------------------------------------------- */
@theme inline {
    --font-sans: var(--font-sans);
    --font-serif: var(--font-serif);
    --font-mono: var(--font-mono);
    --font-heading: var(--font-heading);

    --radius-sm: calc(var(--radius) - 4px);
    --radius-md: calc(var(--radius) - 2px);
    --radius-lg: var(--radius);
    --radius-xl: calc(var(--radius) + 4px);

    --spacing: var(--spacing);

    --color-background: var(--background);
    --color-foreground: var(--foreground);
    --color-card: var(--card);
    --color-card-foreground: var(--card-foreground);
    --color-popover: var(--popover);
    --color-popover-foreground: var(--popover-foreground);
    --color-primary: var(--primary);
    --color-primary-foreground: var(--primary-foreground);
    --color-secondary: var(--secondary);
    --color-secondary-foreground: var(--secondary-foreground);
    --color-muted: var(--muted);
    --color-muted-foreground: var(--muted-foreground);
    --color-accent: var(--accent);
    --color-accent-foreground: var(--accent-foreground);
    --color-destructive: var(--destructive);
    --color-destructive-foreground: var(--destructive-foreground);
    --color-success: var(--success);
    --color-success-foreground: var(--success-foreground);
    --color-warning: var(--warning);
    --color-warning-foreground: var(--warning-foreground);
    --color-info: var(--info);
    --color-info-foreground: var(--info-foreground);
    --color-border: var(--border);
    --color-input: var(--input);
    --color-ring: var(--ring);
    --color-sidebar: var(--sidebar);
    --color-sidebar-foreground: var(--sidebar-foreground);
    --color-sidebar-primary: var(--sidebar-primary);
    --color-sidebar-primary-foreground: var(--sidebar-primary-foreground);
    --color-sidebar-accent: var(--sidebar-accent);
    --color-sidebar-accent-foreground: var(--sidebar-accent-foreground);
    --color-sidebar-border: var(--sidebar-border);
    --color-sidebar-ring: var(--sidebar-ring);
}

:root {
    --radius: 0.625rem;
    --spacing: 0.25rem;
    --font-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    --background: oklch(1 0 0);
    --foreground: oklch(0.145 0 0);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.145 0 0);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.145 0 0);
    --primary: oklch(0.205 0 0);
    --primary-foreground: oklch(0.985 0 0);
    --secondary: oklch(0.97 0 0);
    --secondary-foreground: oklch(0.205 0 0);
    --muted: oklch(0.97 0 0);
    --muted-foreground: oklch(0.49 0 0);
    --accent: oklch(0.97 0 0);
    --accent-foreground: oklch(0.205 0 0);
    --destructive: oklch(0.505 0.225 27.325);
    --destructive-foreground: oklch(0.985 0 0);
    --success: oklch(0.455 0.125 150);
    --success-foreground: oklch(0.985 0.018 155);
    --warning: oklch(0.48 0.11 65);
    --warning-foreground: oklch(0.987 0.022 95);
    --info: oklch(0.475 0.16 250);
    --info-foreground: oklch(0.985 0.015 255);
    --border: oklch(0.922 0 0);
    --input: oklch(0.922 0 0);
    --ring: oklch(0.708 0 0);
    --sidebar: oklch(0.985 0 0);
    --sidebar-foreground: oklch(0.145 0 0);
    --sidebar-primary: oklch(0.205 0 0);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-accent: oklch(0.97 0 0);
    --sidebar-accent-foreground: oklch(0.205 0 0);
    --sidebar-border: oklch(0.922 0 0);
    --sidebar-ring: oklch(0.708 0 0);
}

.dark {
    --background: oklch(0.145 0 0);
    --foreground: oklch(0.985 0 0);
    --card: oklch(0.205 0 0);
    --card-foreground: oklch(0.985 0 0);
    --popover: oklch(0.205 0 0);
    --popover-foreground: oklch(0.985 0 0);
    --primary: oklch(0.922 0 0);
    --primary-foreground: oklch(0.205 0 0);
    --secondary: oklch(0.269 0 0);
    --secondary-foreground: oklch(0.985 0 0);
    --muted: oklch(0.269 0 0);
    --muted-foreground: oklch(0.708 0 0);
    --accent: oklch(0.269 0 0);
    --accent-foreground: oklch(0.985 0 0);
    --destructive: oklch(0.704 0.191 22.216);
    --destructive-foreground: oklch(0.985 0 0);
    --success: oklch(0.7 0.15 155);
    --success-foreground: oklch(0.21 0.05 155);
    --warning: oklch(0.78 0.14 82);
    --warning-foreground: oklch(0.26 0.05 75);
    --info: oklch(0.7 0.14 245);
    --info-foreground: oklch(0.21 0.05 250);
    --border: oklch(1 0 0 / 10%);
    --input: oklch(1 0 0 / 15%);
    --ring: oklch(0.556 0 0);
    --sidebar: oklch(0.205 0 0);
    --sidebar-foreground: oklch(0.985 0 0);
    --sidebar-primary: oklch(0.488 0.243 264.376);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-accent: oklch(0.269 0 0);
    --sidebar-accent-foreground: oklch(0.985 0 0);
    --sidebar-border: oklch(1 0 0 / 10%);
    --sidebar-ring: oklch(0.556 0 0);
}

[x-cloak] {
    display: none !important;
}
```

#### 3.2.4 Source Scripts: `resources/js/admin.js`
```javascript
import '../css/admin.css';
import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';
import { registerBlatUI } from '@blatui/blatui-core.js';

const initBlatUI = (alpine) => {
    if (!alpine || alpine._blatui_admin_registered) {
        return;
    }
    alpine.plugin(anchor);
    alpine.plugin(focus);
    alpine.plugin(collapse);
    registerBlatUI(alpine);
    alpine._blatui_admin_registered = true;
};

document.addEventListener('alpine:init', () => {
    initBlatUI(window.Alpine);
});

if (!window.Alpine) {
    window.Alpine = Alpine;
    initBlatUI(Alpine);
    Alpine.start();
} else if (window.Alpine.version) {
    initBlatUI(window.Alpine);
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

---

## 7. Risks & Mitigation

| Risk | Impact | Mitigation Strategy |
| :--- | :--- | :--- |
| Downstream app doesn't have Node/npm | Asset compilation failure | Precompiled assets in `dist/` are tracked in git and distributed via Composer. Downstream users only run `vendor:publish`. |
| Tailwind v4 utility purging misses dynamic classes in PHP | Styles missing on badges/alerts | `@source "../../src"` scans PHP classes (e.g. `src/Grid/Displayers/Badge.php`). |
| Conflict with host application's Alpine instance | Duplicate stores/plugins error | `initBlatUI` guards against duplicate registration using `_blatui_admin_registered` and checks `window.Alpine`. |
| Downstream user upgrades package but old assets cached | Outdated CSS/JS in public directory | Users re-publish with `--force`. Documented in `admin:publish` guide. |
