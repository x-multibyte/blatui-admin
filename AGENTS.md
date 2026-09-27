# BlatUI Admin

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `x-multibyte/blatui-admin`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Rendering Architecture & Security

BlatUI Admin employs a modernized, security-hardened rendering architecture that completely eliminates legacy Dcat-style PHP string concatenation and raw unescaped Blade output (`{!! !!}`):

- **ViewModel Pattern (View-Model Separation):**
  - PHP classes (`Content`, `Grid`, `Filter`, `Tools`, `Column`, `Row`) act strictly as ViewModels and Data Transfer Objects (DTOs). They hold configuration, state, and business logic.
  - Never concatenate raw HTML strings inside PHP classes (`$html .= '<div...'`). All DOM markup, Tailwind CSS classes, and Alpine.js directives belong exclusively in `resources/views/` Blade templates.

- **Native Laravel Contracts (`Htmlable`):**
  - All rendered UI components (`Filter`, `Tools`, `Column`, `Row`, `Content`) strictly implement `\Illuminate\Contracts\Support\Htmlable`.
  - In Blade templates, render nested components using native escaping `{{ $component }}` (e.g., `{{ $tools }}`, `{{ $filter }}`, `{{ $row->cell($column) }}`).
  - Blade natively checks `$component instanceof Htmlable` and calls `toHtml()`, preventing raw injection while allowing pre-escaped markup.

- **Cell Value Sanitization & Displayers:**
  - `Row::cell(Column $column)` returns `\Illuminate\Support\HtmlString`.
  - Unformatted cell values are automatically sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
  - Built-in displayers (`badge`, `link`, `copyable`, `image`, etc.) pre-escape dynamic parameters and wrap their generated markup in `HtmlString`.

- **Defensive Gatekeeping via ViewComposers:**
  - `LayoutComposer` and `GridComposer` (located under `src/View/Composers/`) are registered in `AdminServiceProvider` for `blatui-admin::layouts.*` and `blatui-admin::grid.*`.
  - ViewComposers inspect variables bound to top-level views, automatically sanitizing untrusted scalar strings to `HtmlString` entities before reaching the Blade engine.


## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.

<!-- context7 -->
Use Context7 MCP to fetch current documentation whenever the user asks about a library, framework, SDK, API, CLI tool, or cloud service — even well-known ones like React, Next.js, Prisma, Express, Tailwind, Django, or Spring Boot. This includes API syntax, configuration, version migration, library-specific debugging, setup instructions, and CLI tool usage. Use even when you think you know the answer — your training data may not reflect recent changes. Prefer this over web search for library docs.

Do not use for: refactoring, writing scripts from scratch, debugging business logic, code review, or general programming concepts.

## Steps

1. Always start with `resolve-library-id` using the library name and what to look up in the library's documentation, unless the user provides an exact library ID in `/org/project` format
2. Pick the best match (ID format: `/org/project`) by: exact name match, description relevance, code snippet count, source reputation (High/Medium preferred), and benchmark score (higher is better). If results don't look right, try alternate names or queries (e.g., "next.js" not "nextjs", or rephrase the question). Use version-specific IDs when the user mentions a version
3. `query-docs` with the selected library ID and what to look up in the library's documentation (not single words), scoped to a single concept. If the question spans multiple distinct concepts (e.g. routing and auth and caching), make a separate `query-docs` call per concept with the same library ID, unless the question is about how the concepts interact — combined queries dilute ranking and return shallow results for each topic
4. Answer using the fetched docs
<!-- context7 -->

<!-- OPENWIKI:START -->

## OpenWiki

This repository has a generated `openwiki/` evidence index. It is optional just-in-time context, not required startup reading.

- Do not enumerate, preload, or search wikis at task start. Use retrieval when the user asks for it, when unfamiliar architecture or dependency behavior materially affects the task, or when source inspection leaves an important uncertainty. Stop once the question is grounded.
- When those conditions apply and OpenWiki retrieval tools are available, use `openwiki_search` for just-in-time context and `openwiki_read` for the relevant complete sections. If search returns `workspace_required`, ask which listed workspace to use and retry with its ID.
- Use `openwiki_list_workspaces` or `openwiki_list_wikis` when workspace membership itself needs to be discovered.
- If the retrieval tools are unavailable, read `openwiki/quickstart.md` and follow its links to the relevant pages.
- Treat source code and tests as authoritative. A brief's unknowns and review items are verification gaps, not automatic requirements.
- Prefer the narrowest quiet validation that proves the changed behavior. Preserve complete failure output.

The scheduled OpenWiki GitHub Actions workflow refreshes the repository wiki. Do not hand-edit generated OpenWiki pages unless explicitly asked; prefer updating source code/docs and letting OpenWiki regenerate.

<!-- OPENWIKI:END -->
