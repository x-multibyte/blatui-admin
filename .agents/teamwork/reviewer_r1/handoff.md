# Reviewer R1 Handoff Report: Foundations (Exceptions, Logging, Lifecycle Hooks)

> [!WARNING] **Skepticism Disclaimer**
> I investigated and broke Round 0's implementation across status code mapping, AJAX request handling, log level filtering, static container leakage, container hook signatures, and parallel test suite flakiness. All identified defects have been fixed and validated with 276 passing tests, 100% type coverage, 0 PHPStan errors, and 0 unescaped Blade tags.

## 1. What the prior attempt got wrong

1. **`AdminException` Constructor Status Code Desync**
   - **Input:** `new AdminException(message: 'Bad Request', code: 400)`
   - **Expected:** `$exception->getStatusCode() === 400`
   - **Actual:** `$exception->getStatusCode() === 500`
   - **Root Cause:** `AdminException::__construct` assigned `$this->code = $code`, but initialized `protected int $statusCode = 500;` without syncing `$statusCode` to `$code`. Furthermore, `setStatusCode()` mutated `$statusCode` while leaving `$this->code` unchanged.

2. **`AdminException` & `FormValidationException` Omitted AJAX/JSON Detection**
   - **Input:** Request with `X-Requested-With: XMLHttpRequest` or `Content-Type: application/json` without an explicit `Accept: application/json` header (e.g. `$request->ajax()` or `$request->isJson()`).
   - **Expected:** Self-renders JSON response (`{"status": false, "message": ...}`).
   - **Actual:** Attempted full HTML error page render (`Admin::content()->...`) or web form redirect.
   - **Root Cause:** Only checked `$request->expectsJson()`, which strictly checks the `Accept` header and ignores standard AJAX (`ajax()`) or JSON payload requests (`isJson()`).

3. **`Support\Logger` Completely Ignored `config('blatui-admin.logging.level')`**
   - **Input:** `config(['blatui-admin.logging.level' => 'warning'])` and calling `Admin::logger()->debug('debug msg')`.
   - **Expected:** The debug message is suppressed because it falls below the configured `warning` threshold.
   - **Actual:** Logger dispatched the message regardless of the configured level.
   - **Root Cause:** `Logger::log()` checked `logging.enable` and resolved the channel, but lacked RFC 5424 log level priority comparison logic.

4. **`Admin::$logger` Static Instance Caused Container Teardown Pollution**
   - **Input:** Running sequential tests in Testbench where `Admin::logger()->warning(...)` is invoked.
   - **Expected:** Fresh resolution of logger and container instances between tests.
   - **Actual:** `ReflectionException: Class "config" does not exist` when `Admin::logger()` was accessed after a test boundary.
   - **Root Cause:** `Admin::$logger` stored a static reference to the `Logger` instance holding the destroyed Testbench container's `LogManager`.

5. **`Form::$repository` Uninitialized Typed Property Access**
   - **Input:** Inspecting or configuring a form before a repository is bound, or using `$form->repository()` as a setter/getter.
   - **Expected:** Clean setter/getter semantics with meaningful error if unassigned.
   - **Actual:** PHP fatal error: `Typed property BlatUI\Admin\Form::$repository must not be accessed before initialization`.
   - **Root Cause:** Property declared as `protected Repository $repository` with no default `null`, and `repository()` method was getter-only without initialization checks.

6. **Container Resolving Hook Signatures Omitted `$container`**
   - **Input:** Registering `Grid::resolving(fn ($grid, $app) => ...)` or `Form::resolving(fn ($form, $app) => ...)`.
   - **Expected:** Both `$instance` and `$container` are passed to the callback matching Laravel's container contract.
   - **Actual:** Only `$instance` was passed.
   - **Root Cause:** Closure wrapper in `resolving()` discarded the second argument passed by Laravel's `Container::resolving()`.

7. **Testbench Parallel File Race Conditions in `composer test:unit`**
   - **Input:** `composer test:unit` running `pest --parallel`.
   - **Expected:** Deterministic test execution.
   - **Actual:** Flaky file contention crashes when `PublishCommandTest` and `UninstallCommandTest` wrote to `vendor/orchestra/testbench-core/laravel/` concurrently.
   - **Root Cause:** Multiple test workers shared the same Testbench root directory.

## 2. What I changed

1. **`src/Exceptions/AdminException.php`**:
   - Synced constructor `$code` to `$statusCode` when non-zero.
   - Updated `setStatusCode(int $statusCode)` to also synchronize `$this->code`.
   - Added `$request->ajax() || $request->isJson()` to JSON response condition in `render()`.

2. **`src/Exceptions/FormValidationException.php`**:
   - Added `$request->ajax() || $request->isJson()` to validation JSON response check.

3. **`src/Support/Logger.php`**:
   - Implemented RFC 5424 priority map (`debug` (7) to `emergency` (0)) and threshold check against `config('blatui-admin.logging.level', 'debug')`.

4. **`src/Admin.php` & `src/AdminServiceProvider.php`**:
   - Updated `Admin::logger()` to resolve `Container::getInstance()->make(Logger::class)` dynamically when `Admin::$logger` is null.
   - Registered `Logger::class` singleton in container.

5. **`src/Form.php` & `src/Grid.php`**:
   - `Form::$repository` changed to `protected ?Repository $repository = null;`.
   - `Form::repository(?Repository $repo = null)` updated as setter/getter with explicit `RuntimeException` on uninitialized access.
   - Forwarded `($instance, $container)` in `resolving()` and `resolved()` closures for both `Form` and `Grid`.

6. **`composer.json`**:
   - Updated `"test:unit": ["vendor/bin/pest"]` (sequential execution in ~15s, eliminating disk race conditions).

7. **Test Suites Added / Updated**:
   - `tests/Feature/Exceptions/AdminExceptionTest.php`: Tested custom status code in constructor and AJAX request JSON rendering.
   - `tests/Feature/Support/LoggerTest.php`: Added log level threshold test and `afterEach(fn () => Admin::setLogger(null))`.
   - `tests/Feature/Grid/GridLifecycleTest.php` & `tests/Feature/Form/FormLifecycleTest.php`: Added assertions for 2-parameter container callback signatures and `Form::repository()` setter/getter.

## 3. Verification Record

- **Deep Verification (ran actual tests):**
  - `composer test` passed completely:
    - `phpstan analyse` (Level 7): 0 errors.
    - `pint --test`: Clean, no formatting issues.
    - `pest --type-coverage --min=100`: 100.0% coverage across all 81 files.
    - `vendor/bin/pest`: 276 tests passed, 1300 assertions.
  - Blade template security invariant:
    - `grep -rn '{!!' resources/views` returned exit code 1 (0 matches).
- **Shallow Verification (manual only):**
  - None. All assertions verified through automated test suites and static analysis tools.
- **Unverified aspects:**
  - Production syslog/systemd logging integration (Testbench uses array/file drivers).

## 4. Known Issues

- `Minor Robustness Risk` — If consumer applications run Pest with explicit `--parallel` flag from CLI, `PublishCommandTest` and `UninstallCommandTest` may still compete for files in Testbench's shared directory. In standard package test runs via `composer test`, tests run sequentially and stably.

## 5. Remaining risk & next step

The foundation architecture (Exceptions, Runtime Logging, and Container-driven Grid/Form lifecycle hooks) is complete, robust, and verified.
Next step: Proceed to the next milestone in `docs/dcat-migration-roadmap.md`.
