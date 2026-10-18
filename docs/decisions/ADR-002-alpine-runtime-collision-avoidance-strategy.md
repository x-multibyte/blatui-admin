# ADR-002: Alpine.js Runtime Collision Avoidance Strategy

## Status
Accepted

## Date
2026-10-18

## Context
In the modern Laravel ecosystem, Alpine.js appears across heterogeneous host setups:
1. **Greenfield Projects**: Fresh installations without Alpine or Livewire.
2. **Livewire v3+ Projects**: Livewire internally bundles and boots Alpine.js, mounting it globally on `window.Alpine`. Installing `alpinejs` via npm and invoking `Alpine.start()` creates a fatal **Duplicate-Alpine Collision** (causing double event dispatches, broken reactivity proxies, and corrupted component trees).
3. **Livewire with Flux Projects**: Flux (Livewire's official UI kit) manages its own theme system. Forcing external theme properties overrides user theme configurations and breaks dark mode.
4. **Standalone Alpine Projects**: Host applications running standalone Alpine (without Livewire) where re-triggering `Alpine.start()` is prohibited.

A naive scaffolding approach that unconditionally overwrites `resources/js/app.js` or installs `alpinejs` via npm inevitably breaks existing applications.

## Decision
Implement an environment-aware **multi-framework wiring matrix** within `EnvironmentDetector` and `ViteWiringManager`:

### 1. Package Installation Matrix (`ProcessRunner`)
- **Greenfield Projects**:
  - Install core and plugins: `npm install -D alpinejs @alpinejs/anchor @alpinejs/collapse @alpinejs/focus`.
- **Livewire / Flux Projects**:
  - **Strictly prohibit installing the `alpinejs` npm package**.
  - Install only the required plugins: `npm install -D @alpinejs/anchor @alpinejs/collapse @alpinejs/focus`.

### 2. Script Wiring Matrix (`ViteWiringManager`)
- **Greenfield Projects**:
  - Safely inject standalone bootstrap import into `resources/js/app.js`:
    ```javascript
    import "./blatui.js";
    ```
- **Livewire / Flux Hosts**:
  - Never invoke `Alpine.start()`. Inject an event listener into `app.js` targeting the `alpine:init` hook:
    ```javascript
    import { registerBlatUI } from './blatui-core.js';

    document.addEventListener('alpine:init', () => {
        registerBlatUI(window.Alpine, { theme: false }); // Pass { theme: false } if Flux is detected to preserve Flux theme state
    });
    ```
- **Standalone Alpine Projects**:
  - Import `blatui-core.js` and register plugins onto the existing `window.Alpine` instance without re-booting the runtime.

All file injections enforce strict idempotency: target files are inspected before writing to ensure statements are never duplicated.

## Alternatives Considered

### 1. Runtime Duck-Typing in the Browser Bundle
- **Description**: Publish a universal JavaScript bundle that checks `if (!window.Alpine) { ... }` at runtime, while always installing `alpinejs` via npm.
- **Drawbacks**: Unnecessarily inflates production bundle sizes; leaves duplicate Alpine versions in `package.json`; fails to prevent build-time version lock conflicts with Livewire.
- **Verdict**: Rejected.

### 2. Manual User Configuration
- **Description**: Leave `app.js` untouched and instruct users to copy-paste configuration snippets.
- **Drawbacks**: High developer friction; undermines the package's promise of an automated, delightful installation experience.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Completely eliminates the widespread "Duplicate Alpine" collision bug in Laravel applications.
  - Guarantees seamless coexistence with Livewire and Flux ecosystems.
  - Ensures non-destructive, idempotent modifications to host JavaScript files.
- **Negative**:
  - Requires environment detection logic across both `composer.json` and `package.json`, backed by comprehensive test matrices.
