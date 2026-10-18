# ADR-006: Code Ownership Model for UI Components (`blatui:add`)

## Status
Accepted

## Date
2026-10-18

## Context
Laravel UI packages typically distribute reusable Blade components through one of two models:
1. **Vendor-Owned View Namespaces**: Views remain encapsulated inside the package's `vendor/` directory and are referenced via a registered namespace (e.g. `blatui-admin::button`). Customizing components requires running `vendor:publish` into `resources/views/vendor/blatui-admin` or overriding templates with complex view composers.
2. **Code Ownership Model**: Popularized by `shadcn/ui` and adopted by BlatUI core (`anousss007/blatui`), clean, readable Blade source files are placed directly into the application's `resources/views/components/ui/` directory. The application developer has complete ownership and control over component markup and styling.

We needed to determine the component distribution architecture for `blatui-admin`.

## Decision
Fully adopt the **Code Ownership Model**, integrating directly with the upstream command `php artisan blatui:add`:

1. **Direct Component Ownership**:
   - Do not encapsulate fundamental UI elements inside opaque vendor view namespaces.
   - During installation, `ArtisanRunner::addBlatUiComponents()` triggers `blatui:add` to generate the required UI component set (`button`, `card`, `input`, `dropdown-menu`, `table`, `dialog`, etc.) directly into `resources/views/components/ui/`.
2. **Conflict Prevention via `StubManager`**:
   - `StubManager::hasExistingComponents()` inspects the target directory prior to component generation:
     - If existing components are detected, the installer prompts for confirmation rather than blindly overwriting them.
     - Overwriting is only performed when the `--force` flag is explicitly provided.
3. **Ecosystem Uniformity**:
   - Aligns with the design philosophy of the upstream `anousss007/blatui` package, allowing developers to use `<x-ui.button>` consistently across their application and administrative views.

## Alternatives Considered

### 1. Vendor-Owned Views (`loadViewsFrom`)
- **Pros**: Clean host filesystem during installation; automatic updates upon `composer update`.
- **Cons**: Rigid customization experience; requires tedious view publishing and overrides; directly conflicts with BlatUI's core philosophy.
- **Verdict**: Rejected.

### 2. Duplicating Stubs Inside `blatui-admin`
- **Pros**: Removes runtime dependency on `blatui:add`.
- **Cons**: Severe code duplication; high ongoing maintenance overhead syncing component stubs with upstream releases.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Developers have 100% control to adjust styles, attributes, and behavior without fighting a package view layer.
  - Consistent component syntax (`<x-ui.*>`) across the entire project.
- **Negative**:
  - Requires handling component existence checks and interactive conflict resolution during installation.
