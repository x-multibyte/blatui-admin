# BlatUI Admin - Security Hardening Handover Plan

> **For the next Agent/Developer:** The user's previous session ended due to token limits. You are picking up immediately after the completion of **Phase 3 (Grid Engine)**. The grid engine is merged to `main`. 
> **Your current goal:** Systematically fix the XSS and rendering security debt inherited from the Dcat-admin architecture.

## 1. Current State & Context
- **Repository:** `git@github.com:x-multibyte/blatui-admin.git` (currently on `main`).
- **Accomplished:** Basic XSS fix applied to `Column::renderCell()` (returns `HtmlString` for displayers, escapes plain values).
- **The Problem:** The Dcat-admin architecture relies heavily on `{!! !!}` (raw HTML rendering) in Blade templates. While `Column::renderCell()` is patched, the rest of the layout, filter, and tool rendering chain is vulnerable to XSS if any user input sneaks into variables like `$title`, `$description`, or `$filter` inputs.

## 2. Identified Vulnerabilities / Risk Areas
1. **Layout / Content Injection:** 
   - File: `src/Layout/Content.php` (`renderFallback` method)
   - Code: `$html .= "<h1 class=\"text-2xl font-bold\">{$this->title}</h1>";`
   - Risk: `$this->title` and `$this->description` are interpolated without `htmlspecialchars`.
2. **View Raw Output Overuse:** 
   - Files: `resources/views/grid/table.blade.php`, `filter.blade.php`, `layouts/app.blade.php`
   - Code: `{!! $tools->render() !!}`, `{!! $filter->render() !!}`, `{!! $field->render() !!}`
   - Risk: Implicit trust that all `render()` methods perfectly escape their internal data.

## 3. Implementation Plan for Next Session

Please execute these tasks sequentially:

### Task 1: Strict `HtmlString` Contract
- **Goal:** Establish a rule that any PHP method returning HTML for Blade must return `Illuminate\Support\HtmlString`.
- **Action:** Refactor `render()` methods in `Content`, `Row`, `Column`, `Filter`, `Tools`, and `Field` to return `HtmlString` instead of `string`. Ensure all internal data interpolations within those methods use `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

### Task 2: Layout & Content Escaping Fixes
- **Goal:** Patch `src/Layout/Content.php` and layout logic.
- **Action:** Escape `$this->title`, `$this->description`, and breadcrumbs before concatenating them into HTML strings in `renderFallback()`.

### Task 3: View Composer Gatekeepers
- **Goal:** Centralize view data validation.
- **Action:** Create `BlatUI\Admin\Http\ViewComposers\GridViewComposer`.
- **Action:** Register it in `AdminServiceProvider` for `blatui-admin::grid.*` and `blatui-admin::layouts.*`. Ensure variables passed to views strictly type-check (e.g., rejecting raw unescaped strings where objects/HtmlStrings are expected).

### Task 4: Content Security Policy (CSP)
- **Goal:** Defense in depth.
- **Action:** Add a CSP header to `BlatUI\Admin\Http\Middleware\Authenticate` or a dedicated security middleware to restrict `script-src` to `'self'` and nonces.

### Task 5: Security Test Suite
- **Goal:** Prevent future regressions.
- **Action:** Create `tests/Security/XssRenderingTest.php`.
- **Action:** Add tests injecting `<script>alert('xss')</script>` into Grid titles, Filter inputs, and Tool configurations, asserting the output is properly entity-encoded.

## How to Resume
In your new session, simply prompt:
> "Read `docs/superpowers/plans/2026-09-27-security-hardening-handover.md` and start executing Task 1 on a new branch named `feature/security-hardening`."
