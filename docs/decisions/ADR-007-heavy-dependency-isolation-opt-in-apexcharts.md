# ADR-007: Heavy Dependency Isolation (Opt-in ApexCharts)

## Status
Accepted

## Date
2026-10-18

## Context
Administrative dashboards frequently feature analytical charts (line charts, bar charts, donut charts). The core BlatUI library provides chart components powered by `apexcharts`.

However, charting libraries impose significant size costs:
- `apexcharts` is a heavy client-side dependency (~150KB minified and compressed, >500KB uncompressed).
- Many administrative panels do not require charting (e.g. standard CRUD portals, settings panels, or user management).
- Enforcing `apexcharts` as an unconditional dependency in `package.json` inflates `node_modules`, increases build times, and penalizes initial page load performance for applications that never render charts.

## Decision
Establish a **strict opt-in isolation policy for ApexCharts**:

1. **Non-Mandatory Peer Dependency**:
   - Do not declare `apexcharts` as a required frontend dependency.
2. **Dual-Trigger Activation**:
   - **CLI Flag**: `--install-apex-charts` allows headless scripts and CI pipelines to explicitly opt in.
   - **Interactive Prompt (`InstallWizard`)**: In interactive mode without the flag, the wizard prompts:
     ```text
     Install ApexCharts for analytical dashboard charts? (yes/no)
     ```
   - **Safe Non-Interactive Default**: In non-interactive or `--dry-run` mode, defaults to `false` unless explicitly requested.
3. **Conditional Execution**:
   - Only when `InstallContext::shouldInstallApexCharts()` evaluates to `true`:
     1. Installs the client package: `npm install -D apexcharts` (using the detected host package manager).
     2. Publishes chart templates: `php artisan vendor:publish --tag=blatui-charts`.

## Alternatives Considered

### 1. Mandatory Unconditional Installation
- **Pros**: Zero prompt friction; all chart components are immediately available.
- **Cons**: Unnecessarily bloats frontend bundles for the majority of CRUD applications that do not use charts.
- **Verdict**: Rejected.

### 2. In-House SVG Charting Engine
- **Pros**: Zero third-party npm dependencies.
- **Cons**: High engineering complexity; reinventing the wheel with an inferior feature set compared to mature libraries like ApexCharts.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Keeps the default package installation footprint minimal, fast, and lean.
  - Provides a frictionless, guided opt-in path for applications that require dashboard analytics.
- **Negative**:
  - Adds a conditional branch in `InstallContext` and installation execution logic.
