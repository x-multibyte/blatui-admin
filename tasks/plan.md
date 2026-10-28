# Implementation Plan: GitHub Pages Documentation Site & Diff-Driven AI Sync Pipeline

## Overview
Build a user-facing documentation site under `pages/docs/` powered by VitePress for navigation, search, and typography, paired with a lightweight PHP Testbench pre-renderer (`scripts/compile-docs-examples.php`) that compiles real BlatUI Blade examples into interactive `<BladePreview>` cards (Preview / Code tabs + copy commands). Configure automated GitHub Pages deployment and an event-driven GitHub Actions workflow that triggers on merges to `main`, analyzes the repository Git diff against `pages/docs/WRITING_GUIDE.md`, verifies that all Blade examples and VitePress pages compile cleanly, and opens or updates a rolling `docs/auto-sync` Pull Request for human review.

## Architecture Decisions
- **Directory & Package Isolation**: Host all GitHub Pages source files under `pages/docs/` and add `/pages export-ignore` + `/docs export-ignore` to `.gitattributes` so downstream Composer installations never download documentation assets.
- **Zero-Duplicate Laravel Skeleton**: Reuse the root `orchestra/testbench` environment via `scripts/compile-docs-examples.php` to render `pages/docs/examples/*.blade.php` into standalone HTML preview artifacts and a JSON manifest consumed by VitePress.
- **Examples as Tests**: Enforce a Pest feature test (`tests/Feature/DocsExamplesRenderTest.php`) that renders every `.blade.php` example through the package's real service provider, ensuring broken documentation examples fail `composer test`.
- **Decoupled, Loop-Safe AI Drafting Workflow**: Trigger `.github/workflows/docs-sync.yml` strictly on `push` to `main` touching code paths (`src/**`, `config/**`, `resources/views/**`, `routes/**`). Never block development merges or releases, and never re-trigger when merging documentation PRs.

## Task List

### Phase 1: Foundation
- [x] Task 1: Initialize `pages/docs/` VitePress skeleton and Composer export isolation

### Phase 2: Core Features (Testbench Blade Pre-renderer & Golden Docs)
- [x] Task 2: Implement Testbench Blade example pre-renderer, `<BladePreview>` component, and Pest verification test
- [x] Task 3: Author golden sample documentation pages and `pages/docs/WRITING_GUIDE.md`

### Checkpoint: Foundation & Local Docs Site (After Tasks 1-3)
- [x] `composer test` passes all 4 quality gates (PHPStan, Pint, 100% Type Coverage, Pest including `DocsExamplesRenderTest`)
- [x] `composer docs:render && npm --prefix pages/docs run build` succeeds with zero dead links
- [x] Core user flow (navigation, search, Preview/Code tabs, copy buttons) verified

### Phase 3: CI/CD Deployment & Diff-Driven Sync Workflows
- [x] Task 4: Configure GitHub Pages deployment workflow (`deploy-pages.yml`) and path filters
- [x] Task 5: Configure diff-driven AI documentation sync workflow (`docs-sync.yml`) and README link

### Checkpoint: Complete
- [x] All acceptance criteria met
- [x] Ready for review

## Risks and Mitigations
| Risk | Impact | Mitigation |
|------|--------|------------|
| CSS/JS collision between VitePress theme and BlatUI Tailwind v4 / Alpine.js | High | Render each `.blade.php` example into an isolated preview document loaded via seamless auto-resizing `<iframe>` inside `<BladePreview>`. |
| Infinite workflow loop on `main` when merging docs PRs | High | Restrict `docs-sync.yml` trigger `paths` strictly to `src/**`, `config/**`, `resources/views/**`, and `routes/**`. |
| AI hallucinating non-existent options or components | High | Enforce `pages/docs/WRITING_GUIDE.md` and require `composer docs:render && npm --prefix pages/docs run build` to succeed before opening/updating the PR. |
| Multiple rapid merges to `main` creating conflicting docs PRs | Med | Maintain a single rolling branch (`docs/auto-sync`) with concurrency control and track the last documented commit SHA. |

## Open Questions
- None (Dual-support for `GEMINI_API_KEY` via `google-github-actions/run-gemini-cli` and `ANTHROPIC_API_KEY` via `anthropics/claude-code-action` can be wired cleanly in `docs-sync.yml`).
