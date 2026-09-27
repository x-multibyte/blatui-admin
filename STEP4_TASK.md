# Task: Step 4 — Eliminate Remaining Raw Blade Output

Repository: `/laravel/packages/x-multibyte/blatui-admin` (branch `feature/rendering-debt`)

Steps 1-3 are already committed and green (109 tests passing). Your job is
**Step 4 only**.

## Goal

`AGENTS.md` states the package has **zero** `{!! !!}` raw output. Five sites
remain. Remove all of them by moving the markup into Blade templates.

## Current raw-output sites

| File | Line | Current |
| --- | --- | --- |
| `resources/views/grid/table.blade.php` | ~106 | `{!! $row->renderCheckbox() !!}` |
| `resources/views/grid/table.blade.php` | ~118 | `{!! $row->renderActions() !!}` |
| `resources/views/layouts/fallback.blade.php` | ~12 | `{!! $row->render() !!}` |
| `resources/views/partials/header.blade.php` | ~37 | `{!! $navbarLeft !!}` |
| `resources/views/partials/header.blade.php` | ~68 | `{!! $navbarRight !!}` |

## Step 4a — The `Grid\Row` semantic problem

`Grid\Row::render()` does **not** render a row of data. It returns
`renderActions()` — the action button bar. That is the reason `{!! !!}` was
needed and why `{{ $row }}` would be misleading.

Fix this properly:

1. Create Blade partials under `resources/views/grid/partials/` holding the
   checkbox markup and the actions-bar markup, moved verbatim from
   `src/Grid/Row.php` (`renderCheckbox()` at ~line 511 and `renderActions()`
   at ~line 417, plus the four `src/Grid/Actions/*.php` renderers and
   `src/Grid/Tools/BatchDelete.php` as needed).
2. `Grid\Row` becomes a pure data holder. It must expose state and accessors
   only — no HTML. Required accessors: `getKey()`, `getResource()`,
   `isActionsDisabled()`, `isShowDisabled()`, `isEditDisabled()`,
   `isDeleteDisabled()`, `isQuickEditDisabled()`, and a `getActions()`
   returning the ordered action list.
3. Blade partials render the markup using `{{ }}` only.

**Behaviour must not change.** The rendered output must be functionally
identical: same CSS classes, same Alpine directives (`x-model="selectedRows"`,
`@click`, `x-data`, the `toggle-grid-filter` window event), same
`name="_row_id[]"` checkbox contract, same form field names.

## Step 4b — The `header.blade.php` navbar case

Inspect what `$navbarLeft` / `$navbarRight` actually are. If they are
`Htmlable` / `HtmlString`, `{!! !!}` is redundant — `{{ }}` behaves
identically. Convert them and confirm output is unchanged.

## Step 4c — `fallback.blade.php`

`{!! $row->render() !!}` iterates `Layout\Row` objects. The class attribute is
now escaped (Step 2), so it is safe, but it still violates the "no PHP string
builders" rule. Move that markup into Blade so `Layout\Row` emits no HTML.

## Test impact — read this carefully

`tests/Unit/GridRowTest.php` has roughly 12 tests asserting on
`renderActions()` and `renderCheckbox()`. You have two options:

- **Keep the methods** as thin delegations that render the new Blade partials
  (simplest, zero test churn), **or**
- **Delete the methods** and update the tests to render the partials directly
  (purer architecture, more churn).

Pick one. Either is acceptable. **State your choice and reasoning in the final
report.** Do not leave the suite red.

## Hard requirements

- `composer test` must end fully green (PHPStan 0 errors, Pint clean, all Pest
  tests passing, type coverage 100%).
- After finishing, `grep -rn '{!!' resources/views` must return **nothing**.
- One commit: `refactor(views): migrate remaining raw Blade output to native escaping`
- Do NOT touch `main`. Do NOT add dependencies. Do NOT reformat unrelated code.

## Report back

1. What changed, file by file.
2. The exact Pest test count before and after.
3. Your choice on 4a and why.
4. Anything unescaped you found that this task did not cover.
5. Anything in this description that was wrong or ambiguous.

Paste the **real output** of the final verification commands. Do not
paraphrase results.
