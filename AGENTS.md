# BlatUI Admin

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `x-multibyte/blatui-admin`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Documentation Authority

This file is the authority for how this package is built and what its constraints are. The documents under `docs/superpowers/` and `.agents/teamwork/` record decisions and plans, but they are not binding on their own.

When two sources disagree, resolve it in this order:

1. **`AGENTS.md`** — the constraints every contributor and agent must follow.
2. **The code** — if this file and the code disagree, the code is what actually ships, and this file is the defect. Fix this file in the same change.
3. **Specs and plans** — records of a decision and its reasoning. A spec that contradicts this file has been superseded.

Binding rules:

- **An architecture decision change updates this file in the same commit.** Adding, removing, or reversing a layer in `src/` is not complete until this file describes the result. A decision recorded only in a spec leaves the next agent reading a contract that no longer matches the tree.
- **Record rejections, not just decisions.** When a design is dropped, say so here and give the reason. The reasoning is what prevents the design from being reinvented.
- **Every spec and plan carries a status header.** `COMPLETE`, `IN PROGRESS`, or `SUPERSEDED`. Superseded documents are retained, not deleted, for the same reason rejections are recorded.
- **Do not schedule work from a superseded plan.** Check its status header first. A plan written before a reversal will instruct you to rebuild the design that was just removed.
- **Never let a status claim stand unverified.** "Staged", "committed", "pushed", "merged" — each is a claim to be reproduced with a command, not asserted.

## Rendering Architecture & Security

BlatUI Admin employs a modernized, security-hardened rendering architecture that completely eliminates legacy Dcat-style PHP string concatenation and raw unescaped Blade output (`{!! !!}`):

- **ViewModel Pattern (View-Model Separation):**
  - PHP classes (`Content`, `Grid`, `Filter`, `Tools`, `Column`, `Row`) act strictly as ViewModels and Data Transfer Objects (DTOs). They hold configuration, state, and business logic.
  - Never concatenate raw HTML strings inside PHP classes (`$html .= '<div...'`). All DOM markup, Tailwind CSS classes, and Alpine.js directives belong exclusively in `resources/views/` Blade templates.
  - `Form\Field\Relation` fields are persisted to pivot tables rather than the model table, and `sync()` failures propagate rather than being swallowed.

- **Native Laravel Contracts (`Htmlable`):**
  - All rendered UI components (`Filter`, `Tools`, `Column`, `Row`, `Content`) strictly implement `\Illuminate\Contracts\Support\Htmlable`.
  - In Blade templates, render nested components using native escaping `{{ $component }}` (e.g., `{{ $tools }}`, `{{ $filter }}`, `{{ $row->cell($column) }}`).
  - Blade natively checks `$component instanceof Htmlable` and calls `toHtml()`, preventing raw injection while allowing pre-escaped markup.

- **Cell Value Sanitization & Displayers:**
  - `Row::cell(Column $column)` returns `\Illuminate\Support\HtmlString`.
  - Unformatted cell values are automatically sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
  - Built-in displayers (`badge`, `link`, `copyable`, `image`, etc.) pre-escape dynamic parameters and wrap their generated markup in `HtmlString`.

- **Blade Escaping as the Sole Security Boundary:**
  - All dynamic data reaches the view through native Blade `{{ }}`, which applies `e()` HTML-entity encoding. No additional sanitization layer runs before the Blade engine.
  - `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` is used only when PHP must build an HTML attribute string; the result is still emitted through `{{ }}`, never through `{!! !!}`.
  - Do not hand-escape a variable that already implements a framework HTML contract — Blade resolves `Htmlable` by calling `toHtml()`.

- **JavaScript Context Requires `Js::from`, Not `htmlspecialchars`:**
  - A value interpolated into a JavaScript string literal inside an Alpine expression must be encoded with `Illuminate\Support\Js::from()`, never with `htmlspecialchars()`. `Js::from()` JSON-encodes and wraps in quotes, so an apostrophe becomes `\u0027` instead of the inert `&#039;` entity.
  - `htmlspecialchars()` is wrong here because its entities are HTML, not JavaScript. Once the browser parses the attribute as JavaScript, `&#039;` is literal text: the user sees it mangled, and a quote can terminate the string early and start injected code.
  - Split variables by destination. JS string literals (`fetch` URLs, CSRF tokens, toast messages, `confirm()` prompts) take `Js::from()`. Plain HTML text (button titles, confirmation copy) takes raw values and relies on Blade's `{{ }}`.
  - `Js::from()` escapes forward slashes as `\/` at its safe default. That is valid JavaScript; assert the escaped form in tests rather than weakening the encoder with `JSON_UNESCAPED_SLASHES`.

- **Grid Tools and Actions Render Through Blade:**
  - `src/Grid/Tools.php` and `src/Grid/{Tools/BatchDelete,Actions/Delete}.php` contain no heredocs. Each render method returns `trim((string) view('blatui-admin::grid.…'))`.
  - `trim()` is required: Blade appends a trailing newline that a PHP heredoc does not.
  - `Tools::render()` wraps each slot in `HtmlString` — the child render methods and `prepend()`/`append()` fragments are already-rendered HTML by contract. Blade's `{{ }}` then calls `toHtml()`, which is why the toolbar view needs no raw output.
  - `Tools::renderBatchActions()` passes `BatchAction` objects, not their rendered strings. `BatchAction` implements `Htmlable`, so `{{ $action }}` routes to `toHtml()` without double-escaping.

- **Rejected: ViewComposer Sanitization Layer:**
  - `LayoutComposer` and `GridComposer` under `src/View/Composers/` were implemented and subsequently removed. Do not reintroduce them.
  - The layer converted untrusted scalars into `HtmlString`. Because Blade routes `HtmlString` through `toHtml()` and then emits it unescaped, the pre-escaping and the native escaping cancelled each other out: the layer provided no defence in depth, and it could not intercept variables holding objects rather than scalars.
  - It was ineffective complexity. The single security boundary is Blade's compile-time escaping, as specified in `docs/superpowers/specs/2026-09-27-rendering-architecture-design.md` section 2.3.



## Artisan Commands

Four commands are registered in `AdminServiceProvider::registerCommands()`. They replace the `blatui-admin:placeholder` command that shipped previously; that signature no longer exists.

| Command | Class | Responsibility |
| :--- | :--- | :--- |
| `admin` | `src/Console/Commands/AdminCommand.php` | Banner, version, and the discovered command list |
| `admin:list` | `src/Console/Commands/ListCommand.php` | The same banner output, with no side effects |
| `admin:publish` | `src/Console/Commands/PublishCommand.php` | Interactive or flag-driven publishing of config, routes, seeders, migrations, views, lang, assets |
| `admin:uninstall` | `src/Console/Commands/UninstallCommand.php` | Roll back migrations and delete every published resource |

- **`PrintsAdminInfo` trait** (`src/Console/Commands/Concerns/PrintsAdminInfo.php`) holds the shared banner. `AdminCommand` and `ListCommand` consume it; this is a real extension point with two consumers, not a helper abstraction.
- **The command list is discovered at runtime** via `$app->all('admin')` and `ksort()`ed. Never hardcode the list — a command registered by a third party must appear without a change here.
- **`Admin::version()`** reads `Composer\InstalledVersions` and returns `'dev'` when the package is absent from the registry, so the banner never renders an empty version.
- **`PublishCommand` delegates to `vendor:publish`**, never to `File::copy()`. The service provider stays the single source of truth for publish paths; a second copy of that mapping is a drift risk.
- **Every registered publish tag has a flag.** If you add a `publishes()` group, add the matching `admin:publish` option and interactive entry in the same change. A tag reachable only by memorising a string is an omission, not a design.
- **Publish and uninstall are symmetric.** Anything `admin:publish` writes, `admin:uninstall` removes.
- **Console `<fg=...>` tags are Symfony Console's own markup**, not Blade output. The rendering contract below governs `resources/views/`; console coloring is native to the console formatter and is out of its scope.

**Rejected from the spec (do not reinvent):**
- A bespoke `--no-interaction` flag. Laravel Prompts already honours Artisan's standard `--no-interaction`.
- Deleting `App\Admin\` on uninstall. The package does not own application code.
- Swallowing the migration rollback failure silently. `UninstallCommand` reports it with `$this->error()` and continues, because the remaining cleanup steps are independent of the rollback.

## Resource Pages

`ResourceController` supplies the CRUD skeleton. The four bundled controllers live in `src/Http/Controllers/Resources/`. The `config('blatui-admin.resources')` maps a key to a controller class and the route file generates from it. Note that **registry key ≠ URL segment**.

**Rejected from the spec (do not reinvent):**
- Nested tree fields
- Multiselect description sub-labels
- Silent pivot-failure tolerance

## Authorization Architecture & Middleware

`BlatUI\Admin\Http\Middleware\Permission` enforces route-level and path-based RBAC permissions:
- **Middleware Aliases**: `admin.auth` (`Authenticate::class`) and `admin.permission` (`Permission::class`) registered in `AdminServiceProvider`.
- **Global Toggle & Whitelist**: `config('blatui-admin.permission.enable')` enables/disables enforcement; `config('blatui-admin.permission.except')` exempts paths (supporting `*` wildcards and `METHOD:path` syntax), automatically normalized against the admin route prefix.
- **Superuser Bypass**: `isAdministrator()` bypasses all permission checks.
- **Route Parameters**: Supports explicit route middleware parameters:
  - `admin.permission:free` — bypass permission check for route.
  - `admin.permission:allow,{role1},{role2}` — allow only users in specified roles.
  - `admin.permission:deny,{role1},{role2}` — deny users in specified roles.
  - `admin.permission:check,{perm1},{perm2}` — allow only users possessing specified permission slugs.
- **Default RBAC Matching**: Iterates `$user->allPermissions()` and tests `$permission->shouldPassThrough($request)`.
- **Denial Behavior**: AJAX/JSON requests receive HTTP 403 JSON (`{"status": false, "message": "..."}`); web requests invoke `abort(403, ...)`.

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
