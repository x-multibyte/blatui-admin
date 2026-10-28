# Architecture & Coexistence

BlatUI Admin is architected around a strict **Four-Tier Installer Pipeline** and non-destructive host integration so it can be installed safely into both greenfield Laravel projects and mature Livewire/Flux codebases.

## Four-Tier Lifecycle Pipeline

1. **Diagnostics (`BlatUI\Admin\Console\Installer\Diagnostics`)**
   `EnvironmentDetector` performs read-only checks on PHP, Laravel, Node.js, lockfiles (`bun.lockb`/`bun.lock`, `pnpm-lock.yaml`, `yarn.lock`, `package-lock.json`), Vite config, Tailwind CSS v4, and installed packages, producing an immutable `DiagnosticReport`.
2. **Actions (`BlatUI\Admin\Console\Installer\Actions`)**
   Headless execution services (`ProcessRunner`, `ViteWiringManager`, `StubManager`, `ArtisanRunner`) perform deterministic operations and expose dry-run descriptions.
3. **Wizard (`BlatUI\Admin\Console\Installer\Wizard`)**
   `InstallWizard` and `InstallContext` orchestrate `laravel/prompts` interactions, dry-run previews, and step-by-step execution.
4. **CLI Entrypoints (`BlatUI\Admin\Console\Commands`)**
   `BlatuiAdminCommand` (`blatui:admin`) and `AdminInstallCommand` (`admin:install`) parse CLI options into `InstallContext` and delegate to `InstallWizard`.

## Runtime Coexistence Matrix

`ViteWiringManager` inspects the host application's dependencies and wires CSS/JS additively without ever causing duplicate Alpine.js instances:

<BladePreview
  name="admin-table-demo"
  title="Environment Wiring Summary"
  command="php artisan blatui:admin --install --dry-run"
  :min-height="260"
/>

### 1. Zero Duplicate-Alpine (`resources/js/app.js`)

- **Greenfield / Standalone**: Installs `alpinejs` via your detected Node package manager and appends:
  ```js
  import "./blatui.js";
  ```
- **Livewire v3 / v4 Detected**: Never installs a duplicate `alpinejs` package. Instead, registers `blatui-core.js` onto Livewire's bundled `window.Alpine`:
  ```js
  import { registerBlatUI } from './blatui-core.js'

  document.addEventListener('alpine:init', () => {
      registerBlatUI(window.Alpine)
  })
  ```
- **Livewire + Flux UI Detected**: Preserves Flux's theme store by passing `{ theme: false }`:
  ```js
  import { registerBlatUI } from './blatui-core.js'

  document.addEventListener('alpine:init', () => {
      registerBlatUI(window.Alpine, { theme: false })
  })
  ```

### 2. Additive W3C/Lightning CSS Wiring (`resources/css/app.css`)

Lightning CSS and Tailwind v4 require all `@import` statements to precede regular CSS rules. `ViteWiringManager::wireCSS()` inspects `resources/css/app.css`:
- If `@import` rules already exist (such as `@import "tailwindcss";`), `@import "./blatui.css";` is inserted immediately after the last `@import` statement.
- If no `@import` rules exist yet, `@import "./blatui.css";` is inserted after leading comments, `@charset`, or statement-only `@layer` declarations, before the first selector block.
