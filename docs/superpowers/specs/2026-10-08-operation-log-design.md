> **Status: COMPLETE (verified 2026-10-08)**
> **Authority:** This document defines the implementation specification for the Operation Log subsystem in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md`, `CLAUDE.md`, and referencing `dcat-admin`.

# 操作日志系统 (Operation Log Subsystem) — Implementation Specification

## 1. Objective

Implement the complete **Operation Log** subsystem in `blatui-admin`:
1. Provide the **Operation Log Resource Page** (`BlatUI\Admin\Http\Controllers\Resources\OperationLogController`) to view and clean up audit logs, resolving the dangling `auth/logs` menu item and `/auth/logs*` permission seeded in `AdminTablesSeeder`.
2. Provide the **Operation Log Middleware** (`BlatUI\Admin\Http\Middleware\OperationLog`) to automatically record administrative HTTP actions into the `admin_operation_log` database table with sensitive input masking and configurable path/method filtering.

## 2. Architecture & Components

### 2.1 OperationLogController
- **File**: `src/Http/Controllers/Resources/OperationLogController.php`
- **Extends**: `BlatUI\Admin\Http\Controllers\ResourceController`
- **Model**: `BlatUI\Admin\Models\OperationLog` (resolved via `config('blatui-admin.database.operation_log_model', OperationLog::class)`)
- **Resource Key**: `logs` (URL prefix: `Admin::url('auth/logs')`)
- **Title**: `Operation Log` (translatable: `__('blatui-admin::admin.operation_log')`)
- **Grid Configuration**:
  - `id`: ID, sortable.
  - `user_id`: Operator Administrator display (`name` or `username`).
  - `method`: HTTP Method badge (GET info, POST success, PUT/PATCH warning, DELETE danger).
  - `path`: Request path.
  - `ip`: Client IP.
  - `input`: Sanitized request payload (formatted/truncated).
  - `created_at`: Creation timestamp with `datetime()`.
  - **Tool & Row Action Permissions**:
    - Disables create button: `$grid->disableCreateButton()`.
    - Disables row actions: `$actions->disableShow()`, `$actions->disableEdit()`, `$actions->disableQuickEdit()`.
    - Retains delete action (`destroy`) and batch delete (`batchDestroy`).
  - **Filters**:
    - `equal('user_id')` (or user select).
    - `equal('method')`.
    - `like('path')`.
    - `like('ip')`.
    - `between('created_at')`.
- **Read-Only Enforcements**:
  - Overrides `create()`, `store()`, `edit()`, `update()` to reject write attempts with HTTP 404 (`abort(404)` or `ResourceNotFoundException`).

### 2.2 OperationLog Middleware
- **File**: `src/Http/Middleware/OperationLog.php`
- **Aliases**: `admin.operation-log` registered in `AdminServiceProvider::registerRouteMiddleware()`.
- **Route Attachment**: Included in `routes/blatui-admin.php` under authenticated admin route group `[Authenticate::class, Permission::class, OperationLog::class]`.
- **Execution Lifecycle & Guards**:
  - If `! config('blatui-admin.operation_log.enable', true)`: skip.
  - If `! Admin::user()`: skip (unauthenticated requests or guests).
  - If request method is not in `config('blatui-admin.operation_log.allowed_methods')`: skip.
  - If request path matches any pattern in `config('blatui-admin.operation_log.except')`: skip (e.g. `auth/logs*` to prevent recursive loop).
  - If logging:
    - Sanitize `$request->input()` by recursively masking keys in `config('blatui-admin.operation_log.secret_fields')` with `'******'`.
    - Persist to `OperationLog` model:
      ```php
      $model = config('blatui-admin.database.operation_log_model', OperationLog::class);
      $model::create([
          'user_id' => Admin::user()->id,
          'path' => substr($request->path(), 0, 255),
          'method' => $request->method(),
          'ip' => $request->ip() ?? '127.0.0.1',
          'input' => json_encode($sanitizedInput, JSON_UNESCAPED_UNICODE),
      ]);
      ```
    - Proceed to `$next($request)`.

### 2.3 Configuration Schema (`config/blatui-admin.php`)
```php
'resources' => [
    'administrators' => 'BlatUI\Admin\Http\Controllers\Resources\AdministratorsController',
    'roles' => 'BlatUI\Admin\Http\Controllers\Resources\RolesController',
    'permissions' => 'BlatUI\Admin\Http\Controllers\Resources\PermissionsController',
    'menus' => 'BlatUI\Admin\Http\Controllers\Resources\MenusController',
    'logs' => 'BlatUI\Admin\Http\Controllers\Resources\OperationLogController',
],

'operation_log' => [
    'enable' => env('ADMIN_OPERATION_LOG_ENABLE', true),
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH'],
    'except' => [
        'auth/logs*',
    ],
    'secret_fields' => [
        'password',
        'password_confirmation',
        '_token',
    ],
],
```

---

## 3. Test Cases Board (Acceptance Criteria)

| # | Test Case Description | Assertion Target (Mutation Check) |
|---|---|---|
| 1 | `OperationLogController` 作为 `logs` 资源注册在配置中，生成 `admin.logs.index`、`destroy` 和 `batch-destroy` 路由 | `config/blatui-admin.php` 中的 `'resources.logs'` 映射以及 `routes/blatui-admin.php` 资源路由遍历 |
| 2 | `GET /admin/auth/logs` 渲染包含数据列表的 Grid（包含用户、请求方法 Badge、路径、IP、输入及时间列） | `OperationLogController::grid()` 中的列定义及 `EloquentRepository` 数据拉取 |
| 3 | `OperationLogController` 的 Grid 禁用新建按钮与行的编辑/查看动作 | `OperationLogController::grid()` 中的 `$grid->disableCreateButton()` 与 `$actions->disableEdit()` / `$actions->disableShow()` |
| 4 | 访问 `create`、`store`、`edit`、`update` 端点均返回 404 状态码，禁止手动伪造或篡改日志 | `OperationLogController` 重写 `create()`, `store()`, `edit()`, `update()` 并抛出 404 / `ResourceNotFoundException` |
| 5 | 单条删除端点 `DELETE /admin/auth/logs/{id}` 能够成功删除指定日志记录 | `OperationLogController` 继承自 `ResourceController::destroy()` 的删除执行链路 |
| 6 | 批量删除端点 `DELETE /admin/auth/logs/batch-delete` 能够成功批量删除指定 IDs 的日志记录 | `OperationLogController` 继承自 `ResourceController::batchDestroy()` 的批量删除执行链路 |
| 7 | Grid 筛选器可通过 `user_id`、`method`、`path`、`ip` 等条件过滤日志列表 | `OperationLogController::grid()` 中的 `$grid->filter(...)` 闭包配置 |
| 8 | 认证管理员发起后台请求时，`OperationLog` 中间件自动将操作信息持久化到 `admin_operation_log` 表 | `BlatUI\Admin\Http\Middleware\OperationLog::handle()` 捕获请求并调用 `OperationLog::create()` |
| 9 | 未登录访客发起的请求不会被 `OperationLog` 中间件记录 | `OperationLog::handle()` 中检查 `Admin::user() !== null` 的守卫判断 |
| 10 | `operation_log.enable = false` 时中间件不记录任何日志 | `OperationLog::handle()` 中检查 `config('blatui-admin.operation_log.enable')` 的逻辑分支 |
| 11 | `operation_log.allowed_methods` 中未包含的 HTTP Method（如仅配置 POST 时发生的 GET 请求）不予记录 | `OperationLog::handle()` 中 `in_array($request->method(), $allowedMethods)` 的校验分支 |
| 12 | 匹配 `operation_log.except` 白名单路径（如 `auth/logs*`）的请求不予记录，避免递归循环记录 | `OperationLog::handle()` 中使用路径匹配跳过白名单的逻辑 |
| 13 | 请求输入中包含 `secret_fields`（如 `password`, `_token`）的字段在入库前被脱敏为 `******` | `OperationLog::sanitizeInput()` 脱敏过滤逻辑 |
| 14 | 中间件已注册为 `admin.operation-log` 别名，并默认挂载在后台认证路由组中 | `AdminServiceProvider::registerRouteMiddleware()` 及 `routes/blatui-admin.php` 中间件组 |
