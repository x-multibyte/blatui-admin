# Documentation Writing Guide & AI Sync Contract

This document defines the strict rules for both human contributors and automated AI documentation workflows (`.github/workflows/docs-sync.yml`) when updating `pages/docs/`.

## 1. Core Principles (Strict Diff-Grounding)

1. **Document Only Implemented Code**: Every CLI option, configuration key, class name, method signature, publish tag, and Blade component mentioned in `pages/docs/` MUST exist in the current repository (`src/`, `config/`, `resources/`, `routes/`, or `vendor/anousss007/blatui/stubs/`).
2. **Never Document Speculative or Planned Features**: Do not copy unimplemented roadmap items from `tasks/`, `SPEC.md`, or commit messages if the corresponding PHP/Blade implementation is not present.
3. **Keep Diffs Minimal and Targeted**: When triggered by a code diff on `main`, update only the documentation sections and examples directly affected by that diff.

## 2. Directory Structure

- `pages/docs/index.md` — Landing page hero and high-level feature summary.
- `pages/docs/guide/*.md` — User-facing guides (installation, CLI options, architecture, components).
- `pages/docs/examples/*.blade.php` — Real Blade component snippets compiled by `scripts/compile-docs-examples.php`.
- `pages/docs/.vitepress/config.mts` — VitePress navigation, sidebar, and search configuration.

## 3. Authoring Interactive `<BladePreview>` Examples

Whenever you document a visual UI component or layout:

1. Create or update a real `.blade.php` snippet in `pages/docs/examples/<example-name>.blade.php`.
2. Embed the preview in Markdown using `<BladePreview>`:
   ```md
   <BladePreview
     name="<example-name>"
     title="Human-Readable Title"
     command="php artisan blatui:admin --install"
   />
   ```
3. Never hardcode raw `<iframe src="...">` tags in Markdown — always use `<BladePreview>` and a real `.blade.php` file in `pages/docs/examples/`.

## 4. Mandatory Verification Commands

Every documentation change MUST pass both verification steps before a commit or Pull Request is created:

```bash
# 1. Compile all Blade examples via Orchestra Testbench and build VitePress
composer docs:render && npm --prefix pages/docs run build

# 2. Run the full PHP quality suite (includes DocsExamplesRenderTest)
composer test
```

If `composer docs:render` fails on a `.blade.php` example or `npm --prefix pages/docs run build` reports a dead link, fix the broken example or link before committing.
