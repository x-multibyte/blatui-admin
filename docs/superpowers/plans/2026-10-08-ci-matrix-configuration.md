# CI Matrix and Static Analysis Configuration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Correct the structural mismatch in `.github/workflows/tests.yml` by simplifying the CI matrix, removing unsupported platforms (Windows, PHP 8.3, prefer-lowest), and disabling fail-fast.

**Architecture:** This is purely a CI configuration and documentation update. No production code in `src/` will be changed. We are updating `composer.json` constraints, the GitHub Actions workflow, and project guidelines to reflect the supported platforms.

**Tech Stack:** GitHub Actions, Composer, Markdown.

**Spec:** `docs/superpowers/specs/2026-10-08-ci-matrix-configuration-design.md`

## Global Constraints

- PHP version floor: `8.4`
- Laravel versions: `12.*`, `13.*`
- OS: `ubuntu-latest` only
- Stability: `prefer-stable` only
- Retain `||` constraints in `composer.json`'s `require-dev` block unmodified.

## Review Focus

*(Empty — I checked the spec and found no input classes or production code failure modes, as this is entirely a CI matrix configuration change without code/test logic.)*

---

### Task 1: Update Composer Constraints

**Files:**
- Modify: `composer.json`

**Interfaces:**
- Consumes: N/A
- Produces: Updated Composer constraints for Task 2's CI verification.

- [ ] **Step 1: Update PHP floor in `composer.json`**

Change the PHP requirement from `^8.3` to `^8.4`. Do not modify the `illuminate/support` constraint or any `require-dev` constraints.

```json
    "require": {
        "php": "^8.4",
        "illuminate/support": "^12.0||^13.0"
    },
```

- [ ] **Step 2: Commit**

```bash
git add composer.json
git commit -m "build: raise PHP floor to 8.4"
```

### Task 2: Simplify CI Matrix in `.github/workflows/tests.yml`

**Files:**
- Modify: `.github/workflows/tests.yml`

**Interfaces:**
- Consumes: Task 1's `composer.json`.
- Produces: A simplified CI matrix that runs 4 jobs instead of failing fast on toolchain bugs.

- [ ] **Step 1: Disable fail-fast and simplify the matrix**

Modify the `strategy` block to remove `os` and `stability`, disable `fail-fast`, and remove PHP `8.3`.

```yaml
    strategy:
      fail-fast: false
      matrix:
        php: ['8.4', '8.5']
        laravel: ['12.*', '13.*']
        include:
          - laravel: '12.*'
            testbench: '10.*'
          - laravel: '13.*'
            testbench: '11.*'
```

- [ ] **Step 2: Update job name and `runs-on`**

Update `runs-on` to `ubuntu-latest` (since `os` is removed from the matrix). Update the `name` to remove the `stability` interpolation and hardcode `ubuntu-latest`.

```yaml
    runs-on: ubuntu-latest
```

```yaml
    name: P${{ matrix.php }} - L${{ matrix.laravel }} - ubuntu-latest
```

- [ ] **Step 3: Update "Install dependencies" step**

Replace `--${{ matrix.stability }}` with `--prefer-stable`.

```yaml
      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}" --no-interaction --no-update
          composer update --prefer-stable --prefer-dist --no-interaction --no-progress --no-scripts
          composer run prepare
```

- [ ] **Step 4: Remove Windows guard from "Type Coverage" step**

Delete `if: runner.os != 'Windows'`.

```yaml
      - name: Type Coverage
        env:
          __PEST_PLUGIN_ENV: 1
        run: composer test:types
```

- [ ] **Step 5: Verify the workflow parses locally (Optional/Best Effort)**

Run a Composer update and test cycle using one of the lanes to ensure `composer.json` is still valid.
```bash
composer require "laravel/framework:12.*" "orchestra/testbench:10.*" --no-update
composer update --prefer-stable --prefer-dist --no-interaction --no-scripts
composer run prepare
composer test
```

- [ ] **Step 6: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "ci: simplify matrix, drop Windows and prefer-lowest"
```

### Task 3: Update Documentation and Templates

**Files:**
- Modify: `AGENTS.md`
- Modify: `.agents/skills/package-compatibility/SKILL.md`
- Modify: `.github/ISSUE_TEMPLATE/bug.yml`

**Interfaces:**
- Consumes: The structural decisions made in Task 2.
- Produces: Updated guidance for future agents and contributors.

- [ ] **Step 1: Document the supported platforms in `AGENTS.md`**

Insert a new `## Supported Platforms` section under `## Package Conventions` (or `## Documentation Authority`) in `AGENTS.md`. It must record the matrix and explicitly state the rejection of Windows and `prefer-lowest`.

```markdown
## Supported Platforms

- **PHP:** 8.4, 8.5
- **Laravel:** 12.*, 13.*
- **OS:** Ubuntu (`ubuntu-latest`)

Windows and `prefer-lowest` dependencies are explicitly **rejected** and unsupported. The Windows CI lane was removed because it never completed a run. The `prefer-lowest` lane was removed because it fails to resolve valid dependency trees (e.g. Testbench 10 vs PHPUnit 13), measuring the toolchain rather than this package's compatibility.
```

- [ ] **Step 2: Clean up `.agents/skills/package-compatibility/SKILL.md`**

Remove all mentions of "Windows CI", "Windows path assumptions", "prefer-lowest behavior", "prefer-stable coverage", "Windows concerns", and "Windows path separators" from the file.

- [ ] **Step 3: Update `.github/ISSUE_TEMPLATE/bug.yml`**

Remove `, Windows 11` from the environment placeholder at line 43.
Change from: `placeholder: macOS 15.0, Ubuntu 24.04, Windows 11`
To: `placeholder: macOS 15.0, Ubuntu 24.04`

- [ ] **Step 4: Commit**

```bash
git add AGENTS.md .agents/skills/package-compatibility/SKILL.md .github/ISSUE_TEMPLATE/bug.yml
git commit -m "docs: record supported platform matrix and removals"
```
