# Implementation Plan: Architecture Optimization - Directory Structure & Placeholder Cleanup

> **Goal**: Expand package skeleton directories (`Contracts`, `Events`, `Models`, `Support`, `resources/assets`, `resources/views/components`) to prepare for modular feature development, and remove obsolete placeholder files (`lang/en/messages.php`, `resources/views/placeholder.blade.php`) while maintaining 100% quality gate compliance.

---

## 1. Context & Objectives

To prepare BlatUI Admin for upcoming feature development, the codebase structure needs to be expanded with dedicated directories following modern Laravel package design idioms:
- `src/Contracts/`: Interface and contract definitions for decoupled architecture.
- `src/Events/`: Package-level lifecycle and domain events.
- `src/Models/`: Internal entity and Eloquent models.
- `src/Support/`: Utility classes and helper functions inspired by `Illuminate\Support` (e.g., Collection, Str, Arr).
- `resources/assets/`: Frontend asset sources (CSS, JS, icons).
- `resources/views/components/`: Reusable package Blade view components.

Simultaneously, early boilerplate placeholder files (`lang/en/messages.php`, `resources/views/placeholder.blade.php`) should be deleted to eliminate unused clutter. The test suite and specification (`SPEC.md`) must be kept consistent with these architectural changes.

---

## 2. Tasks Breakdown

### Task 1: Directory Structure Expansion & Placeholder Cleanup
- Create new directory skeletons with `.gitkeep` for git tracking:
  - `src/Contracts/.gitkeep`
  - `src/Events/.gitkeep`
  - `src/Models/.gitkeep`
  - `src/Support/.gitkeep`
  - `resources/assets/.gitkeep`
  - `resources/views/components/.gitkeep`
  - `lang/en/.gitkeep` (preserve language root for service provider registration)
- Delete redundant placeholder files:
  - Remove `lang/en/messages.php`
  - Remove `resources/views/placeholder.blade.php`
- Update `SPEC.md`:
  - Update Section 6 "Project Structure" to document the expanded directories and their responsibilities.
- **Acceptance Criteria**:
  - All 6 target directories exist and are tracked by git.
  - Obsolete placeholder files are completely removed.
  - `SPEC.md` reflects the accurate directory structure.

### Task 2: Test Suite Refactoring & Full Quality Gates
- Update `tests/Feature/ExampleTest.php`:
  - Replace placeholder file content assertions with robust namespace registration contract assertions:
    - Verify view namespace `blatui-admin` is registered in `view()->getFinder()->getHints()`.
    - Verify translation namespace `blatui-admin` is registered in translator.
- Run full quality verification:
  - `composer test` (PHPStan Level 7, Pint, Pest 100% Type Coverage, 100% passing tests).
- **Acceptance Criteria**:
  - `composer test` passes all 4 quality gates with 0 errors.

---

## 3. Verification Checkpoint
- `git status` displays only the expected directory additions, deletions, test update, and doc update.
- All 81+ tests pass cleanly.
