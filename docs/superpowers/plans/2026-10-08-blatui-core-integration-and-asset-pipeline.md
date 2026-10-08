# BlatUI Core Integration & Precompiled Asset Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate BlatUI foundation packages (`anousss007/blatui`, `tailwind-merge-laravel`, `blade-lucide-icons`), establish a precompiled asset pipeline (`dist/admin.css` & `dist/admin.js`), eliminate external CDN scripts, migrate dark mode to BlatUI's `themeStore`, and wire up symmetric publishing and CI validation.

**Architecture:** Internal Vite build compiling BlatUI foundations (`app.css` & `blatui-core.js`) into standalone `dist/` bundles tracked in git. Assets published to `public/vendor/blatui-admin/` via `blatui-admin-assets` tag. Runtime dependencies required via Composer. Layout and login views load local precompiled assets, and dark mode state is migrated to `themeStore` single-writer model.

**Tech Stack:** PHP 8.4+, Laravel 12/13, Testbench 10/11, Pest 4/5, Tailwind CSS v4, Alpine.js 3, Vite 6, BlatUI 1.3.

**Spec:** [`docs/superpowers/specs/2026-10-08-blatui-core-integration-and-asset-pipeline-design.md`](file:///laravel/packages/x-multibyte/blatui-admin/docs/superpowers/specs/2026-10-08-blatui-core-integration-and-asset-pipeline-design.md)

---

## Global Constraints

- **PHP & Laravel support**: PHP ^8.4, Laravel ^12.0||^13.0, Ubuntu OS only.
- **Rendering & Security contract**: Strictly comply with `AGENTS.md`: Zero raw `{!! !!}` output in Blade, all dynamic output escaped with `{{ }}`, PHP classes act as DTOs/ViewModels without HTML string concatenation.
- **Package isolation**: Downstream applications must require zero Node/npm dependencies; `dist/admin.css` and `dist/admin.js` are precompiled and committed to the repository.
- **Publish & Uninstall symmetry**: Any asset written by `admin:publish` or `admin:install` must be removed by `admin:uninstall`.
- **Single-writer dark mode**: Layout `<html>` class is owned exclusively by `themeStore.apply()`; layout `x-data` must never write `.dark`.

---

## Review Focus

1. **Dark mode dual-writer race**: If `layouts/app.blade.php` keeps `x-data="{ darkMode: ... }"` while `themeStore` also updates `<html>`, theme toggles flip out of sync. Pin with a test verifying only `themeStore` manages theme state.
2. **Missing design tokens**: If `admin.css` trims upstream `--tracking-*`, `--shadow-*`, or `[data-base]` blocks, utilities and theme presets fail silently. Pin with verification that `admin.css` matches upstream foundations.
3. **Asset freshness drift**: If `dist/` is modified without updating sources or sources are modified without rebuilding `dist/`, package distribution drifts. Pin with a CI step `npm ci && npm run build && git diff --exit-code dist/`.
4. **Publish tag registration**: If `AdminServiceProvider` misses `blatui-admin-assets`, `admin:publish --assets` silently succeeds with 0 files. Pin with test asserting all 8 tags and byte-for-byte publishing.
5. **CDN residue**: If any view retains `cdn.jsdelivr.net`, the offline/air-gapped guarantee is broken. Pin with a grep assertion in tests.

---

## Implementation Tasks

### Task 1: Composer Runtime Dependencies & Package Provider Integration

**Files:**
- Modify: `composer.json:19-23`
- Modify: `tests/TestCase.php:38-48`
- Create: `tests/Feature/DependenciesTest.php`

- [ ] **Step 1: Write failing test for runtime dependencies and auto-discovery**
  Create `tests/Feature/DependenciesTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  use BlatUI\BlatuiServiceProvider;
  use TailwindMerge\Laravel\TailwindMergeServiceProvider;
  use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;

  test('blatui core packages and peer dependencies are registered', function () {
      expect(class_exists(BlatuiServiceProvider::class))->toBeTrue()
          ->and(class_exists(TailwindMergeServiceProvider::class))->toBeTrue()
          ->and(class_exists(BladeLucideIconsServiceProvider::class))->toBeTrue();

      expect(app()->providerIsLoaded(BlatuiServiceProvider::class))->toBeTrue()
          ->and(app()->providerIsLoaded(TailwindMergeServiceProvider::class))->toBeTrue()
          ->and(app()->providerIsLoaded(BladeLucideIconsServiceProvider::class))->toBeTrue();
  });
  ```

- [ ] **Step 2: Run test to confirm it fails**
  Run: `vendor/bin/pest tests/Feature/DependenciesTest.php`
  Expected: Fails due to missing classes.

- [ ] **Step 3: Update `composer.json` with required packages**
  Add `"anousss007/blatui": "^1.3"`, `"gehrisandro/tailwind-merge-laravel": "^1.0"`, and `"mallardduck/blade-lucide-icons": "^1.21||^2.0"` to `require`.
  Update `tests/TestCase.php` `getPackageProviders()` to include the three providers.

- [ ] **Step 4: Run composer update**
  Run: `composer update --no-interaction`

- [ ] **Step 5: Run test to verify it passes**
  Run: `vendor/bin/pest tests/Feature/DependenciesTest.php`
  Expected: 1 test passed.

- [ ] **Step 6: Commit changes**
  Commit: `feat(deps): add blatui core, tailwind-merge, and blade-lucide-icons dependencies`

---

### Task 2: Asset Build Tooling, Sources & Precompiled Bundles

**Files:**
- Create: `package.json`
- Create: `vite.config.js`
- Create: `resources/css/admin.css`
- Create: `resources/js/admin.js`
- Create: `dist/admin.css`
- Create: `dist/admin.js`
- Create: `package-lock.json`
- Create: `tests/Feature/AssetBundleTest.php`

- [ ] **Step 1: Write failing test for asset bundle presence and non-emptiness**
  Create `tests/Feature/AssetBundleTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  use Illuminate\Support\Facades\File;

  test('precompiled assets exist and are non-empty in dist directory', function () {
      $cssPath = dirname(__DIR__, 2) . '/dist/admin.css';
      $jsPath = dirname(__DIR__, 2) . '/dist/admin.js';

      expect(File::exists($cssPath))->toBeTrue()
          ->and(File::size($cssPath))->toBeGreaterThan(1024)
          ->and(File::exists($jsPath))->toBeTrue()
          ->and(File::size($jsPath))->toBeGreaterThan(1024);
  });
  ```

- [ ] **Step 2: Run test to confirm it fails**
  Run: `vendor/bin/pest tests/Feature/AssetBundleTest.php`
  Expected: Fails because `dist/admin.css` does not exist.

- [ ] **Step 3: Create `package.json` and `vite.config.js`**
  Write `package.json` with Vite 6, Tailwind v4, and Alpine dependencies.
  Write `vite.config.js` with ESM-safe `fileURLToPath(import.meta.url)` path resolution and `chunkFileNames: 'admin-[name]-[hash].js'`.

- [ ] **Step 4: Create `resources/css/admin.css` and `resources/js/admin.js`**
  Copy upstream foundations `app.css` from `vendor/anousss007/blatui/stubs/foundations/app.css` into `resources/css/admin.css`, rewriting `@source` paths.
  Write `resources/js/admin.js` with `alpine:init` registering `registerBlatUI(window.Alpine, { darkMode: 'system' })` and `window.Alpine` bootstrap.

- [ ] **Step 5: Install npm packages and build `dist/`**
  Run: `npm install && npm run build`
  Verify: `dist/admin.css` and `dist/admin.js` exist.

- [ ] **Step 6: Run test to verify it passes**
  Run: `vendor/bin/pest tests/Feature/AssetBundleTest.php`
  Expected: 1 test passed.

- [ ] **Step 7: Commit changes**
  Commit: `feat(assets): configure vite build pipeline and generate precompiled assets`

---

### Task 3: Service Provider Asset Publishing & Symmetry Across Artisan Commands

**Files:**
- Modify: `src/AdminServiceProvider.php:85-88`
- Modify: `src/Console/Commands/InstallCommand.php:45-48`
- Modify: `testbench.yaml:6-10`
- Modify: `tests/Feature/PublishTest.php:7-22`
- Modify: `tests/Feature/PublishCommandTest.php`
- Modify: `tests/Feature/UninstallCommandTest.php`
- Modify: `tests/Feature/InstallCommandTest.php`

- [ ] **Step 1: Write failing assertions for `blatui-admin-assets` publish tag and command integration**
  Update `tests/Feature/PublishTest.php` to include `'blatui-admin-assets'`.
  Update `tests/Feature/PublishCommandTest.php` with:
  - `cleanUpPublishedResources()` removing `public_path('vendor/blatui-admin')`.
  - test verifying `admin:publish --assets` publishes `admin.css` and `admin.js`.
  Update `tests/Feature/UninstallCommandTest.php` to assert `public_path('vendor/blatui-admin')` is deleted.
  Update `tests/Feature/InstallCommandTest.php` to assert `admin:install` publishes assets.

- [ ] **Step 2: Run tests to confirm failures**
  Run: `vendor/bin/pest tests/Feature/PublishTest.php tests/Feature/PublishCommandTest.php tests/Feature/UninstallCommandTest.php tests/Feature/InstallCommandTest.php`
  Expected: Fails on missing `blatui-admin-assets` publish tag.

- [ ] **Step 3: Register `blatui-admin-assets` in `AdminServiceProvider.php`**
  Add publishes mapping:
  ```php
  $this->publishes([
      __DIR__.'/../dist' => public_path('vendor/blatui-admin'),
  ], ['blatui-admin', 'blatui-admin-assets']);
  ```

- [ ] **Step 4: Update `InstallCommand.php` and `testbench.yaml`**
  In `InstallCommand::handle()`, add:
  ```php
  $this->callSilent('vendor:publish', ['--tag' => 'blatui-admin-assets']);
  ```
  In `testbench.yaml`, ensure `blatui-admin-assets` is listed under `workbench.assets`.

- [ ] **Step 5: Run tests to verify they pass**
  Run: `vendor/bin/pest tests/Feature/PublishTest.php tests/Feature/PublishCommandTest.php tests/Feature/UninstallCommandTest.php tests/Feature/InstallCommandTest.php`
  Expected: All tests pass.

- [ ] **Step 6: Commit changes**
  Commit: `feat(publish): register blatui-admin-assets tag with install and uninstall symmetry`

---

### Task 4: View Layer CDN Removal & Dark Mode Migration to `themeStore`

**Files:**
- Modify: `resources/views/layouts/app.blade.php:12-32`
- Modify: `resources/views/auth/login.blade.php:10-15`
- Modify: `resources/views/partials/header.blade.php:65-80`
- Create: `tests/Feature/ViewAssetAndDarkModeTest.php`

- [ ] **Step 1: Write failing test for CDN elimination and single-writer dark mode**
  Create `tests/Feature/ViewAssetAndDarkModeTest.php`:
  ```php
  <?php

  declare(strict_types=1);

  use Illuminate\Support\Facades\File;

  test('views do not reference cdn.jsdelivr.net', function () {
      $viewFiles = File::allFiles(resource_path('views'));

      foreach ($viewFiles as $file) {
          $content = $file->getContents();
          expect($content)->not->toContain('cdn.jsdelivr.net')
              ->and($content)->not->toContain('@tailwindcss/browser');
      }
  });

  test('layout does not use duplicate darkMode x-data state', function () {
      $layout = File::get(resource_path('views/layouts/app.blade.php'));
      expect($layout)->not->toContain("localStorage.getItem('theme')")
          ->and($layout)->not->toContain(":class=\"{ 'dark': darkMode }\"")
          ->and($layout)->toContain("vendor/blatui-admin/admin.css")
          ->and($layout)->toContain("vendor/blatui-admin/admin.js");
  });

  test('header dark mode toggle delegates to themeStore', function () {
      $header = File::get(resource_path('views/partials/header.blade.php'));
      expect($header)->toContain('$store.theme.toggle()')
          ->and($header)->toContain('$store.theme.isDark');
  });
  ```

- [ ] **Step 2: Run test to confirm it fails**
  Run: `vendor/bin/pest tests/Feature/ViewAssetAndDarkModeTest.php`
  Expected: Fails due to existing CDN references and layout `darkMode` state.

- [ ] **Step 3: Update `layouts/app.blade.php` and `auth/login.blade.php`**
  In `layouts/app.blade.php`:
  - Remove CDN `<script>` tags.
  - Add `<link rel="stylesheet" href="{{ asset('vendor/blatui-admin/admin.css') }}">` and `<script defer src="{{ asset('vendor/blatui-admin/admin.js') }}"></script>`.
  - Remove `darkMode` from `x-data`, remove `x-init="$watch('darkMode', ...)"`, and remove `:class="{ 'dark': darkMode }"`.
  In `auth/login.blade.php`:
  - Replace CDN `<script>` tags with local assets.
  In `partials/header.blade.php`:
  - Update dark mode toggle button to `@click="$store.theme.toggle()"`.
  - Update sun/moon icon `x-show` bindings to `$store.theme.isDark`.

- [ ] **Step 4: Run test to verify it passes**
  Run: `vendor/bin/pest tests/Feature/ViewAssetAndDarkModeTest.php`
  Expected: 3 tests passed.

- [ ] **Step 5: Commit changes**
  Commit: `feat(views): eliminate runtime CDNs and migrate dark mode to themeStore`

---

### Task 5: Architecture Authority Documentation & CI Freshness Check

**Files:**
- Modify: `AGENTS.md`
- Modify: `.github/workflows/tests.yml`

- [ ] **Step 1: Update `AGENTS.md` with Asset Pipeline & Distribution Subsystem**
  Add section "Asset Pipeline & Distribution Subsystem" to `AGENTS.md` detailing:
  - Precompiled assets in `dist/` tracked in git.
  - Complete elimination of runtime CDN scripts.
  - `themeStore` single-writer dark mode.
  - Symmetric asset publishing via `blatui-admin-assets`.
  - Explicitly document rejection of host-app Vite coupling (`laravel-vite-plugin` + `@vite`).

- [ ] **Step 2: Update `.github/workflows/tests.yml` with asset freshness check**
  Add a build step before composer tests:
  ```yaml
      - name: Verify asset freshness
        run: |
          npm ci
          npm run build
          git diff --exit-code dist/
  ```

- [ ] **Step 3: Run full verification suite**
  Run:
  ```bash
  composer test
  grep -rn "jsdelivr\|cdn\.min\.js" resources/views
  grep -rn '{!!' resources/views
  npm ci && npm run build && git diff --exit-code dist/
  ```
  Expected: 0 errors across all checks.

- [ ] **Step 4: Update specification status to `COMPLETE`**
  In `docs/superpowers/specs/2026-10-08-blatui-core-integration-and-asset-pipeline-design.md`, update status header from `IN PROGRESS` to `COMPLETE`.

- [ ] **Step 5: Commit changes**
  Commit: `docs: document asset pipeline subsystem in AGENTS.md and add CI freshness check`

---

## Plan Self-Review Checklist

- [x] Every task specifies exact file paths and line ranges.
- [x] Every code change is driven by a TDD failing test first.
- [x] Rejection records and binding rules from `AGENTS.md` are respected.
- [x] Single-writer dark mode transition is completely covered.
- [x] Full verification gate (`composer test`, asset freshness, zero CDN, zero unescaped `{!!`) is enforced.
