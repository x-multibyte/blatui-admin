> **Status: IN PROGRESS**
> **Authority:** This document defines the CI matrix and static-analysis configuration for `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md` and `CLAUDE.md`.

# CI 矩阵与静态检查配置 — Design Specification

## 1. Objective

Correct a structural mismatch in `.github/workflows/tests.yml` that has made the `tests` workflow continuously red since its introduction, and record the supported-platform decision that follows from it.

Four changes, all configuration-level:

1. Raise the PHP floor from `8.3` to `8.4` in `composer.json`.
2. Delete the `prefer-lowest` dependency-stability lane.
3. Delete the Windows lane, and the `8.3` PHP version from the matrix.
4. Set `fail-fast: false` so a single failing combination cannot mask the others.

No production code changes. `src/` is untouched.

## 2. Diagnosis

The workflow has reported `Type coverage below expected: 100.0%, currently 99.6%` on every run. The shortfall is `src/Support/Logger.php` at 75%; the other 70 files report 100%.

### 2.1 The gap is not in this package's code

`BlatUI\Admin\Support\Logger` extends `Psr\Log\AbstractLogger`, which supplies eight level-specific methods (`emergency` … `debug`) via `Psr\Log\LoggerTrait`. Those eight methods are declared in `vendor/`, not in `src/`. The type-coverage analyser attributes them to the `class Logger` line at `src/Support/Logger.php:16`, and `src/` contains no annotation that can change that verdict.

### 2.2 Controlled reproduction

Each row below is a fresh dependency resolution on a copy of this repository at commit `b5e577a`, followed by `composer test:types`. Only the stated variable differs.

| Run | `pest-plugin-type-coverage` | `psr/log` | `Logger.php` | Total |
| :--- | :--- | :--- | :--- | :--- |
| `prefer-lowest` | 4.0.0 | 2.0.0 | **75%** | 99.6% |
| psr/log raised only | 4.0.0 | **3.0.0** | **75%** | 99.6% |
| `prefer-stable` | **4.0.4** | 3.0.2 | **100%** | 100% |

Row 2 isolates `psr/log` and eliminates it as the cause: `psr/log` 2.0.0 and 3.0.0 both declare `log($level, string|Stringable $message, array $context = [])` identically, and the 75% verdict is unchanged. The cause lies between `pest-plugin-type-coverage` 4.0.0 and 4.0.4.

### 2.3 The `prefer-stable` lane was already green

Row 3 is the operative fact. `composer.json` line 30 declares `"pestphp/pest-plugin-type-coverage": "^4.0||^5.0"`, and commit `b5e577a` (`typehint toResponse parameter with mixed`) fixed the real defect. With `prefer-stable`, both the Laravel 12 and Laravel 13 lanes report 100%.

No `src/` edit accompanies this spec, because none is needed.

### 2.4 Why the failure was never isolated

`.github/workflows/tests.yml` line 22 sets `fail-fast: true`. Every recorded run reports one `failure` and every other job `cancelled`:

```
failure    P8.4 - L13.* - prefer-lowest - ubuntu-latest
cancelled  P8.3 - L12.* - prefer-stable - ubuntu-latest
cancelled  P8.5 - L13.* - prefer-stable - windows-latest
... (23 cancelled)
```

The `prefer-stable` and Windows lanes never executed. They were neither passing nor failing — they were unmeasured, and the single red `prefer-lowest` job was read as evidence about the whole matrix. `fail-fast` is what allowed an unresolved lane to be mistaken for a repository-wide defect, and it is why three successive commits (`3054df1`, `89ea18a`, `b5e577a`) treated a stale-toolchain symptom as the object of repair.

### 2.5 A second, independent defect in the same lane

`prefer-lowest` also cannot resolve a dependency tree at all under the current constraints:

```
orchestra/testbench-core v10.15.0 conflicts with phpunit/phpunit 13.3.6.
```

The lane was therefore not measuring Laravel 12 minimum compatibility. It was measuring whether the Pest 4.0.0 ecosystem composes, which is a property of the development toolchain, not of this package's runtime compatibility.

## 3. Design

### 3.1 Supported platform statement

| Dimension | Supported | Removed |
| :--- | :--- | :--- |
| PHP | 8.4, 8.5 | 8.3 |
| Laravel | 12.*, 13.* | — |
| Testbench | 10.*, 11.* | — |
| OS | `ubuntu-latest` | `windows-latest` |

PHP 8.3 and Windows are dropped as explicit non-support, not as untested accidents. The Windows lane has never completed a run, so no Windows-specific behaviour has ever been under test.

### 3.2 `composer.json`

One line changes:

```diff
 "require": {
-    "php": "^8.3",
+    "php": "^8.4",
     "illuminate/support": "^12.0||^13.0"
 },
```

The `||` constraints in `require-dev` are **retained unchanged**. This is a verified requirement, not an oversight:

- Laravel 12 + `pest-plugin-laravel` 5.x is unsatisfiable — Testbench 10 pins `symfony/process ^7.2` while the plugin pulls `symfony/process` 8.x.
- Laravel 13 + Testbench 11 resolves cleanly with Pest 5.

Composer therefore selects per lane: Laravel 12 lands on Pest 4.7.8 / type-coverage 4.0.4, and Laravel 13 lands on Pest 5.3.0 / type-coverage 5.0.2. Both selections are the fixed versions from §2.2. Collapsing the `||` into a single major would break the Laravel 12 lane outright.

### 3.3 `.github/workflows/tests.yml`

```diff
     strategy:
-      fail-fast: true
+      fail-fast: false
       matrix:
-        os: [ubuntu-latest, windows-latest]
-        php: ['8.3', '8.4', '8.5']
+        php: ['8.4', '8.5']
         laravel: ['12.*', '13.*']
-        stability: [prefer-lowest, prefer-stable]
         include:
           - laravel: '12.*'
             testbench: '10.*'
           - laravel: '13.*'
             testbench: '11.*'

-    name: P${{ matrix.php }} - L${{ matrix.laravel }} - ${{ matrix.stability }} - ${{ matrix.os }}
+    name: P${{ matrix.php }} - L${{ matrix.laravel }} - ${{ matrix.os }}
```

With `os` reduced to a single value it collapses to a scalar, and `runs-on` becomes `${{ matrix.os }}` unchanged in form. The matrix resolves to four jobs: `8.4×12`, `8.4×13`, `8.5×12`, `8.5×13`.

The `stability` dimension is removed from the name because it is no longer a varying axis. `--${{ matrix.stability }}` in the install step is replaced with the literal `--prefer-stable`.

### 3.4 The Windows type-coverage guard

The current workflow carries:

```yaml
      - name: Type Coverage
        if: runner.os != 'Windows'
```

With Windows removed this guard is dead weight and is deleted. Type coverage now runs on every job.

## 4. Verification

Measured on copies of this repository with the §3.2 `composer.json`, one fresh resolution per lane.

| Lane | Laravel | Testbench | Pest | type-coverage | PHPStan | Pint | Tests | Coverage |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 12 | 12.69.3 | 10.12.0 | 4.7.8 | 4.0.4 | 0 errors | pass | 300/300 | 100% |
| 13 | 13.35.0 | 11.3.0 | 5.3.0 | 5.0.2 | 0 errors | pass | 300/300 | 100% |

`src/Support/Logger.php` reports 100% in both lanes. Both lanes satisfy `composer test` in full: `analyse`, `lint:check`, `test:types`, `test:unit`.

### 4.1 Reproduction command

```bash
composer require "laravel/framework:12.*" "orchestra/testbench:10.*" --no-update
composer update --prefer-stable --prefer-dist --no-interaction --no-scripts
composer run prepare
composer test
```

Repeat with `13.*` / `11.*` for the second lane.

## 5. Documentation updates

`AGENTS.md` is the authority for this package's constraints. Per its Documentation Authority rule — an architecture decision change updates the file in the same commit — the following must change alongside the workflow:

- Record the supported matrix (PHP 8.4+, Laravel 12/13, ubuntu-latest only) and that `prefer-lowest` and Windows are rejected, with the reason. A rejection recorded here is what prevents the next contributor from reinstating the lane as an apparent improvement.

`.agents/skills/package-compatibility/SKILL.md` currently instructs reviewers to check "Windows CI", "Windows path assumptions", and "prefer-lowest behavior". Those instructions no longer describe this repository. They are corrected in the same commit.

`.github/ISSUE_TEMPLATE/bug.yml` offers `Windows 11` as an environment option. Given §3.1 states non-support rather than untested, the option is misleading and is removed in the same commit.

## 6. Rejected alternatives

**Raise the type-coverage threshold instead of enforcing 100%.** Rejected. The defect it would have masked is real and already fixed; lowering the bar discards a working gate to accommodate a lane that should not exist.

**Keep `prefer-lowest`, pin `pest-plugin-type-coverage` to `^4.0.4`.** Rejected. It preserves a lane that cannot resolve a dependency tree (§2.5) and that measures the toolchain rather than this package's runtime compatibility. It also retains `php ^8.3` support the maintainer has stated is unused.

**Keep Windows, fix the path-related failures.** Rejected. No Windows job has ever completed, so there are no failures to fix; the maintainer does not target Windows.

**Restore `fail-fast: true` for quota economy.** Rejected. It is the direct cause of §2.4. At four jobs the runner cost is negligible, and a matrix that cannot report which combination failed is not a gate.

**Modify `src/Support/Logger.php` to resolve the 75%.** Rejected. The eight untyped methods are declared in `vendor/psr/log`, not in `src/`. No edit to this package can alter the verdict.

**Declare `psr/log: ^3.0` in `require`.** Rejected. §2.2 row 2 shows the version is not the cause, so this would constrain consumers to satisfy a constraint that fixes nothing.

## 7. Scope

In scope: `composer.json`, `.github/workflows/tests.yml`, `AGENTS.md`, `.agents/skills/package-compatibility/SKILL.md`, `.github/ISSUE_TEMPLATE/bug.yml`.

Out of scope: any file under `src/`, `config/`, `routes/`, `database/`, `resources/`, `tests/`. This spec introduces no behaviour change to the package.