# BlatUI Admin

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `x-multibyte/blatui-admin`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

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

## Architecture & Decisions

The package installer lifecycle is strictly split into 4 decoupled tiers:
1. `Diagnostics` (`src/Console/Installer/Diagnostics/`): Read-only environmental checks producing immutable `DiagnosticReport`.
2. `Actions` (`src/Console/Installer/Actions/`): Headless execution toolkit (`ProcessRunner`, `ViteWiringManager`, `StubManager`, `ArtisanRunner`).
3. `Wizard` (`src/Console/Installer/Wizard/`): Prompts-based orchestrator handling interactive flow and dry-run execution.
4. `CLI Commands` (`src/Console/Commands/`): Thin command entrypoint `BlatuiAdminCommand` (`blatui:admin {--install}`) and compatibility proxy `AdminInstallCommand` (`admin:install`).

All major architectural decisions are documented in [`docs/decisions/`](docs/decisions/README.md):
- [ADR-001: Four-Tier Lifecycle Architecture](docs/decisions/ADR-001-four-tier-installer-lifecycle-pipeline.md)
- [ADR-002: Alpine.js Collision Avoidance Strategy](docs/decisions/ADR-002-alpine-runtime-collision-avoidance-strategy.md)
- [ADR-003: Additive W3C/Lightning CSS-Compliant Wiring](docs/decisions/ADR-003-additive-w3c-compliant-css-wiring.md)
- [ADR-004: Pure `laravel/prompts` Console UI](docs/decisions/ADR-004-single-channel-console-ui-with-laravel-prompts.md)
- [ADR-005: Command Namespace Migration & Proxy](docs/decisions/ADR-005-command-namespace-migration-and-compatibility-proxy.md)
- [ADR-006: Code Ownership Component Model (`blatui:add`)](docs/decisions/ADR-006-code-ownership-model-for-ui-components.md)
- [ADR-007: Opt-in ApexCharts Isolation](docs/decisions/ADR-007-heavy-dependency-isolation-opt-in-apexcharts.md)

## Critical Invariants & Rules

- **Zero Duplicate-Alpine**: NEVER install `alpinejs` when Livewire or Flux is present in the host.
- **Additive CSS/JS**: NEVER overwrite host `resources/css/app.css` or `resources/js/app.js`.
- **Pure Prompts**: Use `laravel/prompts` exclusively for CLI UI. Never use `Command::$components`.
- **Quality Gates**: All code MUST pass `composer test` (PHPStan Level 7, Pint, 100% Type Coverage, Pest).
- **No Production Leaks**: `declare(strict_types=1);` on all files; no `env()`, `dd()`, `dump()`, or `exit()` in `src/`.

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **blatui-admin** (184 symbols, 193 relationships, 5 execution flows).

> Index stale? Run `node .gitnexus/run.cjs analyze --index-only` from the project root — it auto-selects an available runner. No `.gitnexus/run.cjs` yet? Bootstrap with `npx`, `bunx`, or `pnpm dlx` — e.g. `bunx gitnexus@latest analyze` (npm 11 npx crash; #1939).

## Always Do

- **MUST run impact before editing.** Use `impact({target: "symbolName", direction: "upstream"})` or `node .gitnexus/run.cjs impact "symbolName" --direction upstream --repo .`; report callers, processes, and risk. Never substitute grep for graph analysis.
- **MUST analyze graph changes before committing.** Use `detect_changes({scope: "all"})` (MCP) or `node .gitnexus/run.cjs detect-changes --scope all --repo .` (CLI fallback). `partial: true` or `truncated: true` is not a clean check — a zero means unseen, not unaffected; re-run it. For regression review: `detect_changes({scope: "compare", base_ref: "main"})` or `node .gitnexus/run.cjs detect-changes --scope compare --base-ref "main" --repo .`.
- MUST warn on HIGH/CRITICAL `risk` pre-edit; never use `riskSharedAxes` to waive a HIGH/CRITICAL `risk` warning. Compare File/symbol: MCP File omits axes; Graph-RAG expands File.
- **MUST treat `risk: UNKNOWN` as unresolved, not as low.** An empty caller set is not evidence the symbol is unused — it can also mean the callers are not resolvable by the index (plain-object property access, dynamic dispatch, cross-language calls). `impact` pairs `UNKNOWN` with a `riskNote` saying so. Confirm with a text search before treating the symbol as safe to change or delete; do not proceed on the strength of a zero.
- **MUST use `query({search_query: "concept"})` for concepts/flows, `context({name: "symbolName"})` for a named symbol, or `impact` for blast radius, on read-only callers, dependencies, imports, or execution flow.** Graph first; text search only for empty/`UNKNOWN`/literals.
- For security review, `explain({target: "fileOrSymbol"})` lists taint findings (source→sink flows; needs `analyze --pdg`).

## Never Do

- NEVER edit a function, class, or method before MCP/CLI impact analysis.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis, and never read `UNKNOWN` as an all-clear — it means the walk could not answer, which is the one verdict that requires confirming by other means.
- NEVER rename symbols with find-and-replace — use `rename` which understands the call graph.
- NEVER commit before MCP/CLI graph change analysis.

## Resources

| Resource | Use for |
| --- | --- |
| `gitnexus://repo/blatui-admin/context` | Codebase overview, check index freshness |
| `gitnexus://repo/blatui-admin/clusters` | All functional areas |
| `gitnexus://repo/blatui-admin/processes` | All execution flows |
| `gitnexus://repo/blatui-admin/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
| --- | --- |
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
