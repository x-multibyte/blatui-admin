# Project: BlatUI Admin Rendering Architecture Refactoring

> **Status: SUPERSEDED (2026-09-28).** This is the planning artifact of the `agy /teamwork-preview` run. It is retained as a record of that run, not as a work queue. Do not schedule work from the milestone table below.
>
> Where the actual project stands:
> - Milestone 1 (native Grid contracts) — **DONE**, and later reversed in direction: the PHP classes are now pure DTOs with no `Htmlable`/`Renderable` interfaces and no `render()` methods.
> - Milestone 2 (Blade view modernisation) — **DONE**. `grep -rn '{!!' resources/views` returns zero matches.
> - Milestone 3 (ViewComposer gatekeepers, features 12-15) — **VOID**. The Composer layer was implemented and then deliberately removed; `src/View/Composers/` does not exist. See spec section 2.3 and commit `2585c5d`.
> - Milestone 4 (AGENTS.md) — **DONE**.
> - Milestone 5 (verification) — **PARTIAL**: `composer test` is green (PHPStan 0 errors, Pint clean, 126 Pest tests / 651 assertions, 100% coverage). CSP middleware was never built.
> - Remaining open work: the filter-field migration to Blade templates, tracked in `docs/superpowers/plans/2026-09-27-rendering-architecture-refactor.md`.
>
> Current authority: `docs/superpowers/specs/2026-09-27-rendering-architecture-design.md` plus the rendering contract in `AGENTS.md`.

## Architecture
- **ViewModel Separation**: PHP classes (`Content`, `Grid`, `Filter`, `Tools`, `Row`, `Column`) serve as ViewModels/DTOs. No raw HTML string concatenation in PHP.
- **Native Contracts**: All UI components implement `\Illuminate\Contracts\Support\Htmlable`.
- **Blade Escaping**: Replace raw `{!! !!}` with native Blade escaping `{{ }}` across table, filter, and app views.
- **Defensive Composers**: *(VOID — removed 2026-09-28. `GridComposer` and `LayoutComposer` are no longer part of the architecture. Blade's `HtmlString` handling made the layer self-cancelling.)*
- **Documentation**: Modernize `AGENTS.md` with rendering architecture and security guidelines.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | `Column` Htmlable Contract | `Column` implements `Htmlable, Stringable` with `toHtml(): string` returning column label | M1 | ORIGINAL_REQUEST R1 |
| 2 | `Row` Htmlable Contract | `Row` implements `Arrayable, Htmlable, Renderable, Stringable` with `toHtml(): string` and `render(): string` | M1 | ORIGINAL_REQUEST R1 |
| 3 | `Row::cell()` HtmlString Return | Refactor `Row::cell(Column $column): HtmlString` to wrap cell output in `HtmlString` | M1 | ORIGINAL_REQUEST R1 |
| 4 | Safe Displayer Pass-Through | Displayer outputs (badges, links, images, datetime) pre-escaped in `HtmlString` pass through without double-escaping | M1 | ORIGINAL_REQUEST R1 |
| 5 | Grid Proxies Htmlable Support | Anonymous class proxies `$toolsProxy` and `$filterProxy` in `Grid::render()` implement `Htmlable` and `toHtml(): string` | M1 | Survey Finding |
| 6 | Filter Proxy Htmlable Support | Anonymous class proxy `$filterProxy` in `Filter::render()` implements `Htmlable` and `toHtml(): string` | M1 | Survey Finding |
| 7 | Grid Unit Tests Modernization | Update `GridColumnTest`, `GridRowTest`, `GridFilterToolsTest` assertions to reflect `Htmlable` and `HtmlString` | M1 | Acceptance Criteria |
| 8 | `table.blade.php` Modernization | Replace `{!! $filter->render() !!}`, `{!! $tools->render() !!}`, `{!! $row->cell($column) !!}` with `{{ }}` | M2 | ORIGINAL_REQUEST R2 |
| 9 | `filter.blade.php` Modernization | Replace `{!! $field->render() !!}` with `{{ $field }}` | M2 | ORIGINAL_REQUEST R2 |
| 10 | `layouts/app.blade.php` Modernization | Replace `{!! is_string($content) ? ... !!}` with `{{ $content }}` | M2 | ORIGINAL_REQUEST R2 |
| 11 | `Content::render()` HtmlString Pass | Ensure `Content::render()` passes `'content' => new HtmlString($this->renderRows())` | M2 | Survey Finding |
| 12 | `GridComposer` Implementation | Create `BlatUI\Admin\View\Composers\GridComposer` to sanitize scalar variables | M3 | ORIGINAL_REQUEST R3 |
| 13 | `LayoutComposer` Implementation | Create `BlatUI\Admin\View\Composers\LayoutComposer` to sanitize scalar variables | M3 | ORIGINAL_REQUEST R3 |
| 14 | AdminServiceProvider Wiring | Register ViewComposers in `AdminServiceProvider::boot()` | M3 | ORIGINAL_REQUEST R3 |
| 15 | ViewComposer Security Tests | Comprehensive tests in `tests/Security/XssRenderingTest.php` for Composers | M3 | Acceptance Criteria |
| 16 | Architectural Documentation | Add Rendering Architecture & Security Guidelines to `AGENTS.md` | M4 | ORIGINAL_REQUEST R4 |
| 17 | E2E & Hardening Verification | Full Pest tests pass, PHPStan passes with 0 errors, Pint formatting passes with 0 violations | M5 | Acceptance Criteria |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M1: Native Grid Contracts | Features 1-7 (`Column`, `Row`, `Grid` proxies, `Filter` proxy, unit tests) | none | DONE |
| 2 | M2: Blade View Modernization | Features 8-11 (`table.blade.php`, `filter.blade.php`, `app.blade.php`, `Content.php`) | M1 | IN_PROGRESS |
| 3 | M3: ViewComposer Gatekeepers | Features 12-15 (`GridComposer`, `LayoutComposer`, `AdminServiceProvider`, tests) | M2 | PLANNED |
| 4 | M4: Architectural Docs | Feature 16 (`AGENTS.md`) | M3 | PLANNED |
| 5 | M5: E2E Verification & Hardening | Feature 17 (Pest, PHPStan, Pint, Security Audit) | M1, M2, M3, M4 | PLANNED |

## Code Layout
- `src/Grid/Column.php`: Column component definition
- `src/Grid/Row.php`: Row component definition
- `src/Grid.php`: Grid container & proxies
- `src/Grid/Filter.php`: Filter component & proxy
- `src/Layout/Content.php`: Content layout & rendering
- `resources/views/grid/table.blade.php`: Table Blade template
- `resources/views/grid/filter.blade.php`: Filter Blade template
- `resources/views/layouts/app.blade.php`: App layout Blade template
- `src/View/Composers/GridComposer.php`: Grid view composer
- `src/View/Composers/LayoutComposer.php`: Layout view composer
- `src/AdminServiceProvider.php`: Package service provider
- `AGENTS.md`: Architectural guidelines
- `tests/`: Pest test suite
