# Task List: GitHub Pages Documentation Site & Diff-Driven AI Sync Pipeline

## Phase 1: Foundation

## Task 1: Initialize `pages/docs/` VitePress skeleton and Composer export isolation

**Description:** Create the `pages/docs/` directory structure with VitePress configured for `/blatui-admin/` base path, local search, and sidebar navigation. Isolate documentation files and build artifacts in `.gitattributes` and `.gitignore` so Composer archives and PHP quality gates remain clean.

**Acceptance criteria:**
- [x] `.gitattributes` includes `/pages export-ignore` and `/docs export-ignore`.
- [x] `.gitignore` ignores `pages/docs/node_modules/`, `pages/docs/.vitepress/dist/`, `pages/docs/.vitepress/cache/`, and `pages/docs/public/previews/`.
- [x] `pages/docs/package.json` and `pages/docs/.vitepress/config.mts` are configured and build cleanly.

**Verification:**
- [x] Tests pass: `composer test`
- [x] Build succeeds: `npm --prefix pages/docs run build`
- [x] Manual check: `git archive HEAD | tar -t | grep '^pages/'` returns empty once committed.

**Dependencies:** None

**Files likely touched:**
- `.gitattributes`
- `.gitignore`
- `pages/docs/package.json`
- `pages/docs/.vitepress/config.mts`
- `pages/docs/index.md`

**Estimated scope:** Medium (5 files)

---

## Phase 2: Core Features

## Task 2: Implement Testbench Blade example pre-renderer and `<BladePreview>` component

**Description:** Create `scripts/compile-docs-examples.php` using the root `orchestra/testbench` container and `BlatUI\Admin\AdminServiceProvider` to compile `pages/docs/examples/*.blade.php` into standalone HTML preview files (`pages/docs/public/previews/*.html`) and a JSON manifest (`pages/docs/.vitepress/previews.json`). Register the `<BladePreview>` Vue component in VitePress for BlatUI-style Preview / Code tab switching and one-click copy, and add a Pest test to guarantee every example renders without error.

**Acceptance criteria:**
- [x] `composer docs:render` (`@php scripts/compile-docs-examples.php`) renders all `.blade.php` examples in `pages/docs/examples/` and fails with non-zero exit code on Blade errors.
- [x] `<BladePreview name="..." command="..." />` renders interactive Preview (iframe sandbox with dark mode sync) and Code tabs with copy buttons.
- [x] `tests/Feature/DocsExamplesRenderTest.php` verifies that all documentation Blade examples render cleanly during `composer test`.

**Verification:**
- [x] Tests pass: `composer test`
- [x] Build succeeds: `composer docs:render && npm --prefix pages/docs run build`
- [x] Manual check: Inspect generated `pages/docs/public/previews/*.html` and `pages/docs/.vitepress/previews.json`.

**Dependencies:** Task 1

**Files likely touched:**
- `scripts/compile-docs-examples.php`
- `composer.json`
- `pages/docs/.vitepress/theme/index.ts`
- `pages/docs/.vitepress/theme/components/BladePreview.vue`
- `tests/Feature/DocsExamplesRenderTest.php`

**Estimated scope:** Medium (5 files)

---

## Task 3: Author golden sample documentation pages and `pages/docs/WRITING_GUIDE.md`

**Description:** Write the initial golden sample documentation pages covering actual implemented features of `x-multibyte/blatui-admin` (Installation, Interactive Wizard & Options, Architecture & Coexistence, and a real `<BladePreview>` example), plus `pages/docs/WRITING_GUIDE.md` defining strict evidence-based rules for AI documentation updates.

**Acceptance criteria:**
- [x] `pages/docs/guide/getting-started.md` and `pages/docs/guide/architecture.md` accurately document current CLI options, Alpine collision avoidance, and CSS wiring with live `<BladePreview>` examples.
- [x] `pages/docs/WRITING_GUIDE.md` codifies diff-grounded rules, file structure conventions, and mandatory build verification steps.

**Verification:**
- [x] Tests pass: `composer test`
- [x] Build succeeds: `composer docs:render && npm --prefix pages/docs run build`
- [x] Manual check: Verify rendered HTML output in `pages/docs/.vitepress/dist/`.

**Dependencies:** Task 2

**Files likely touched:**
- `pages/docs/WRITING_GUIDE.md`
- `pages/docs/guide/getting-started.md`
- `pages/docs/guide/architecture.md`
- `pages/docs/examples/button-demo.blade.php`

**Estimated scope:** Medium (4 files)

---

## Checkpoint: After Tasks 1-3
- [x] All tests pass (`composer test`)
- [x] Documentation site builds without errors (`composer docs:render && npm --prefix pages/docs run build`)
- [x] Core user flow works end-to-end
- [x] Review with human before proceeding

---

## Phase 3: CI/CD Deployment & Diff-Driven Sync Workflows

## Task 4: Configure GitHub Pages deployment workflow (`deploy-pages.yml`) and path filters

**Description:** Create `.github/workflows/deploy-pages.yml` to build and deploy `pages/docs/.vitepress/dist` to GitHub Pages via `actions/deploy-pages`, and update `.github/workflows/tests.yml` with path filters so documentation-only edits do not trigger unnecessary PHP matrix runs.

**Acceptance criteria:**
- [x] `.github/workflows/deploy-pages.yml` runs `composer docs:render` and `npm --prefix pages/docs run build`, uploading the artifact and deploying to GitHub Pages on `main`.
- [x] `.github/workflows/tests.yml` ignores `pages/**` and `docs/**` on push/PR when no PHP or test files are modified.

**Verification:**
- [x] Tests pass: `composer test`
- [x] Build succeeds: `composer docs:render && npm --prefix pages/docs run build`
- [x] Manual check: Validate workflow YAML structure and permissions (`pages: write`, `id-token: write`).

**Dependencies:** Task 3

**Files likely touched:**
- `.github/workflows/deploy-pages.yml`
- `.github/workflows/tests.yml`

**Estimated scope:** Small (2 files)

---

## Task 5: Configure diff-driven AI documentation sync workflow (`docs-sync.yml`) and README link

**Description:** Create `.github/workflows/docs-sync.yml` triggered when code changes (`src/**`, `config/**`, `resources/views/**`, `routes/**`) land on `main`. The workflow computes the Git diff, runs the official AI Action (supporting `GEMINI_API_KEY` via `google-github-actions/run-gemini-cli` or `ANTHROPIC_API_KEY` via `anthropics/claude-code-action`) guided by `pages/docs/WRITING_GUIDE.md`, validates the build with `composer docs:render && npm --prefix pages/docs run build`, and opens/updates a rolling `docs/auto-sync` PR. Add the documentation link to `README.md`.

**Acceptance criteria:**
- [x] `.github/workflows/docs-sync.yml` triggers only on code path changes to `main` (and `workflow_dispatch`), preventing infinite loops.
- [x] Workflow validates documentation changes with `composer docs:render && npm --prefix pages/docs run build` before creating/updating the `docs/auto-sync` PR.
- [x] `README.md` includes a clear link to the GitHub Pages documentation site.

**Verification:**
- [x] Tests pass: `composer test`
- [x] Build succeeds: `composer docs:render && npm --prefix pages/docs run build`
- [x] Manual check: Inspect `.github/workflows/docs-sync.yml` trigger conditions and PR creation logic.

**Dependencies:** Task 3, Task 4

**Files likely touched:**
- `.github/workflows/docs-sync.yml`
- `README.md`

**Estimated scope:** Small (2 files)

---

## Checkpoint: Complete
- [x] All acceptance criteria met
- [x] `composer test` and `composer docs:render && npm --prefix pages/docs run build` pass cleanly
- [x] Ready for review
