# ADR-004: Single-Channel Console UI Standard (`laravel/prompts`)

## Status
Accepted

## Date
2026-10-18

## Context
Laravel 10 introduced `laravel/prompts` as the official console interaction library, providing modern terminal rendering, responsive prompts, spinners (`spin()`), and tables (`table()`).

Earlier installer implementations mixed two incompatible output mechanisms:
1. Classic `Illuminate\Console\Command::$components` (`$this->components->info()`, `$this->components->twoColumnDetail()`).
2. `laravel/prompts` primitives (`confirm()`, `spin()`).

Mixing these approaches caused significant visual and engineering inconsistencies:
- **Disjointed Visual Presentation**: `components` and `prompts` use conflicting terminal indentation, padding, color palettes, and cursor reset logic.
- **Inconsistent Progress Indication**: `components` lacks dynamic progress spinners, resulting in abrupt transitions between animated spinners and raw stdout lines.
- **Fragile Headless Handling**: Degraded differently in non-interactive/CI environments.

## Decision
Enforce a **single-channel console UI standard based exclusively on `laravel/prompts`**:

1. **Exclusive UI Primitives**:
   - Forbid using `Illuminate\Console\Command::$components` or raw `$this->info()` / `$this->error()` in the installer flow.
   - Standardize all console interactions and reporting on `laravel/prompts`:
     - `intro()` / `outro()`: Consistent lifecycle banners.
     - `table()`: Formatted diagnostic reports and dry-run action summaries.
     - `spin()`: Animated progress for time-consuming tasks (npm installs, asset publishing, migrations, component generation).
     - `confirm()` / `select()`: Interactive option collection.
     - `note()` / `warning()` / `error()`: Structured status feedback.
2. **Decoupled Architecture**:
   - Encapsulate the entire console experience inside `InstallWizard`, freeing it from direct inheritance on specific Artisan `Command` instances.

## Alternatives Considered

### 1. Classic Laravel Command Components (`Command::$components`)
- **Pros**: Established convention in older Laravel commands.
- **Cons**: Outdated aesthetic; lacks `spin()` spinners; inferior interactive table rendering.
- **Verdict**: Rejected.

### 2. Hybrid Mixed Mode
- **Description**: Use `Command::$components` for informational text and `prompts` only for input questions.
- **Cons**: Indentation and layout glitches; jarring contrast in terminal scrollbacks; high cognitive overhead for maintainers.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Consistent, polished, modern CLI experience aligned with official Laravel developer tools (Pint, Herd, Sail).
  - Clear testability via standard Prompts fakes and assertions.
- **Negative**:
  - Requires `laravel/prompts` as a core dependency (included by default in Laravel 11+).
