# ADR-003: Additive, W3C/Lightning CSS-Compliant Wiring

## Status
Accepted

## Date
2026-10-18

## Context
In Laravel 11/12+ applications, the default frontend toolchain adopts Vite alongside Tailwind CSS v4+. Tailwind v4 introduces a CSS-first configuration paradigm (replacing `tailwind.config.js` with `@import "tailwindcss";` and CSS custom properties).

Injecting UI library stylesheets into existing host applications (`resources/css/app.css`) introduces two key constraints:
1. **Preventing Destruction of User Styles**: Host applications frequently configure brand fonts, color variables, and base layer styles in `app.css`. Overwriting `app.css` erases user customizations.
2. **W3C & Lightning CSS Specification Compliance**: Modern CSS bundlers (such as Lightning CSS used in Vite 6+ / Tailwind v4) enforce W3C CSS specifications strictly: all `@import` statements must precede all other CSS rules (except `@charset` and `@layer`). Appending `@import "./blatui.css";` to the end of a file containing custom class declarations causes build failures: `@import rules must precede all rules aside from @charset and @layer statement`.

## Decision
Establish an **additive, specification-compliant insertion strategy** within `ViteWiringManager`:

1. **Zero Overwrite Policy**:
   - The host's `resources/css/app.css` must never be overwritten or replaced with a static stub.
2. **Specification-Compliant Placement**:
   - When injecting `@import "./blatui.css";`:
     - If the file is empty, write the import directly.
     - If the file contains existing imports (such as `@import "tailwindcss";` or Google Fonts `@import url(...);`), place the BlatUI import immediately after the existing imports, ensuring it precedes all subsequent CSS class or element rules.
3. **Tailwind Version Awareness**:
   - `EnvironmentDetector` inspects the installed `tailwindcss` version:
     - **Tailwind v4+**: Executes automated, additive wiring.
     - **Tailwind v3**: Flags a `WARN` in the diagnostic report with official migration instructions (`npx @tailwindcss/upgrade`). Prevents mutating incompatible stylesheets to avoid breaking the host build.
4. **Strict Idempotency**:
   - Checks file contents before writing; skips insertion if `blatui.css` is already referenced.

## Alternatives Considered

### 1. Pre-baked Template Overwrite
- **Description**: Replace the host `app.css` entirely with a pre-configured package stub.
- **Drawbacks**: Destructive; completely wipes out existing custom styles and configuration in established projects.
- **Verdict**: Rejected.

### 2. Blind File-End Append (EOF)
- **Description**: Append `@import "./blatui.css";` to the end of the file.
- **Drawbacks**: Violates the W3C CSS specification and triggers fatal Lightning CSS build errors whenever custom CSS rules exist above the insertion point.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Full compatibility with modern Tailwind v4+ and Lightning CSS bundlers.
  - Safe, non-destructive integration into existing host stylesheets.
- **Negative**:
  - Requires structural inspection of existing CSS content to locate valid insertion boundaries, supported by dedicated unit tests.
