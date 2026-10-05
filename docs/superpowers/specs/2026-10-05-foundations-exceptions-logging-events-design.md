> **Status: COMPLETE (verified 2026-10-05)**
> **Authority:** This document defines the implementation specification for Package Foundations (Exception Handling, Global Logging, and Container-Driven Lifecycle Hooks) in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md`.

# 扩展包基础设施 (Foundations: Exceptions, Logging, Container Lifecycles) — Implementation Specification

## 1. Objective

构建并完善 `x-multibyte/blatui-admin` 的三大基础支撑体系：
1. **异常处理体系 (Exception Handling)**：建立标准分层的 `AdminException` 异常树，提供自渲染（Self-Rendering）双通道响应（JSON / Fetch 与后台 Web 错误视图），消除散落的 `abort()` 与裸奔的未捕获异常。
2. **全局运行时日志处理器 (Global Logging System)**：区别于 `admin_operation_log` 数据库业务审计表，为整个扩展包提供基于 PSR-3 / Monolog 的诊断与运行时上下文日志器 (`Admin::logger()`)，支持自动注入管理员会话与请求追踪上下文。
3. **基于 Laravel 容器的 UI 构建生命周期钩子 (Container-Driven Lifecycle Hooks)**：彻底摒弃 Dcat 旧版私有 `Context` 数组字典的土法，也坚决不空造冗余的事件类；直接利用 **Laravel 官方服务容器（Service Container）原生维护的 `resolving()` / `afterResolving()` 依赖注入机制**，原生打通 `Grid` 与 `Form` 的生命周期扩展能力。

---

## 2. Current State (Evidenced)

1. **异常处理现状**：
   - 权限拦截在 `BlatUI\Admin\Http\Middleware\Permission:74` 直接调用 `abort(403, ...)`。
   - 资源删除在 `AdministratorsController:144` 和 `RolesController:91` 手动返回 JSON 或抛出 HTTP 403。
   - 缺失统一的包级异常基类，Web 请求在遇到未捕获异常时直接跳出后台布局，降级为 Laravel 默认错误页，破坏用户体验。
2. **日志体系现状**：
   - 代码中仅存在 `src/Models/OperationLog.php`（对应数据库表 `admin_operation_log`），用于持久化用户操作记录。
   - 整个扩展包在认证失败、权限拒绝、数据保存异常、Pivot 中间表同步故障等关键执行路径上**零运行时日志**，无法进行系统级排障与指标追踪。
3. **UI 构建钩子现状**：
   - 当前 `Grid::make()` 和 `Form::make()` 采用硬编码 `new static($repository)`，完全绕开了 Laravel 服务容器。
   - 服务提供者或第三方扩展无法在构造期通过容器注入通用默认项（如全局筛选条件、通用动作）。

---

## 3. Target Architecture & Components

```
BlatUI\Admin\
├── Exceptions\
│   ├── AdminException.php                 (根抽象异常，实现 render() 双通道)
│   ├── ResourceNotFoundException.php      (404: 资源记录不存在)
│   ├── PermissionDeniedException.php      (403: 权限拦截)
│   ├── FormValidationException.php        (422: 数据校验失败，携带 errorBag)
│   └── ConfigurationException.php         (500: 包配置缺失或非法)
├── Support\
│   └── Logger.php                         (包装 Psr\Log\LoggerInterface，上下文自动注入)
├── Grid.php                               (接入容器解析 app(static::class) 与 resolving / resolved)
└── Form.php                               (接入容器解析 app(static::class) 与 resolving / resolved)
```

---

### 3.1 异常处理架构 (`BlatUI\Admin\Exceptions`)

#### 3.1.1 根异常类：`BlatUI\Admin\Exceptions\AdminException`
File: `src/Exceptions/AdminException.php`
- 继承 `\RuntimeException`，实现 `\Throwable`。
- **属性**：
  - `protected int $statusCode = 500;`
  - `protected array $context = [];`
  - `protected ?string $title = null;`
- **核心契约方法**：
  - `public function getStatusCode(): int`
  - `public function getContext(): array`
  - `public function withContext(array $context): static`
  - `public function getTitle(): string`
- **自渲染机制 (`render(Request $request): Response`)**：
  利用 Laravel 11/12 原生支持的异常自渲染契约，无需侵入 `bootstrap/app.php`：
  - **JSON 通道 (`$request->expectsJson()`)**：
    返回统一结构与正确 HTTP 状态码：
    ```json
    {
      "status": false,
      "message": "错误提示信息",
      "code": 403,
      "errors": []
    }
    ```
  - **Web 通道**：
    通过 `Admin::content()` 在后台标准布局框架内渲染优雅的错误视图：
    ```php
    return response()->view('blatui-admin::errors.page', [
        'title' => $this->getTitle(),
        'message' => $this->getMessage(),
        'statusCode' => $this->getStatusCode(),
    ], $this->getStatusCode());
    ```

#### 3.1.2 领域细分异常类
1. **`PermissionDeniedException`**：
   - 状态码：`403`
   - 默认标题：` __('blatui-admin::admin.deny') ` 或 `'Permission Denied'`
2. **`ResourceNotFoundException`**：
   - 状态码：`404`
   - 默认标题：`'Resource Not Found'`
3. **`FormValidationException`**：
   - 状态码：`422`
   - 专有属性：`protected array $errors = [];`
   - JSON 响应包含 `errors` 键，Web 响应重定向并回填 Flash `$errors`。
4. **`ConfigurationException`**：
   - 状态码：`500`
   - 默认标题：`'Configuration Error'`

#### 3.1.3 视图层安全规范 (`resources/views/errors/page.blade.php`)
- **严格遵循 `AGENTS.md` 渲染架构**：
  - 严禁出现 `{!! !!}`。
  - 使用原生 `{{ $title }}` 与 `{{ $message }}` 转义输出。
  - 采用 Tailwind CSS v4 样式，配合 Lucide 图标与返回控制台（Dashboard）按钮。

---

### 3.2 全局运行时日志体系 (`BlatUI\Admin\Support\Logger`)

#### 3.2.1 配置项规范 (`config/blatui-admin.php`)
在 `config/blatui-admin.php` 新增 `logging` 节点：
```php
'logging' => [
    'enable'  => env('ADMIN_LOGGING_ENABLE', true),
    'channel' => env('ADMIN_LOG_CHANNEL', null), // null 时自动继承默认通道
    'level'   => env('ADMIN_LOG_LEVEL', 'debug'),
],
```

#### 3.2.2 上下文感知日志器：`BlatUI\Admin\Support\Logger`
File: `src/Support/Logger.php`
- 包装 `Illuminate\Log\LogManager`，实现 `Psr\Log\LoggerInterface`。
- **自动上下文注入 (Context Enrichment)**：
  在调用 `debug`, `info`, `warning`, `error` 时，自动聚合当前运行上下文并与用户传入的 `$context` 深度合并：
  ```php
  [
      'admin_user_id' => Admin::id(),
      'admin_user'    => Admin::user()?->username,
      'ip'            => request()->ip(),
      'method'        => request()->method(),
      'path'          => request()->path(),
  ]
  ```
- **门面与单例集成**：
  - 在 `src/Admin.php` 暴露：
    - `public static function logger(): Logger`
    - `public static function log(string $level, string $message, array $context = []): void`
  - 在 `src/Facades/Admin.php` 声明对应注解 `@method static Logger logger()`。

#### 3.2.3 关键执行路径日志埋点
- **认证事件**：在 `AuthController` 中，登录成功输出 `info`，登录失败输出 `warning`（记录尝试用户名与来源 IP），注销输出 `info`。
- **权限拦截**：中间件拒绝访问时，输出 `warning` 级别日志（记录用户 ID、拒绝路由与请求方法）。
- **数据异常**：`ResourceController` 捕获到模型保存或多对多中间表 `sync()` 失败时，输出 `error` 级别日志及完整堆栈。

---

### 3.3 基于 Laravel 容器与依赖注入的原生生命周期钩子

#### 3.3.1 容器化改造核心原理
在 Dcat Admin 中，生命周期回调是通过维护在 `Admin::context()` 内的私有数组实现的；在现代 Laravel 架构中，**Laravel 的 IoC 服务容器天然具备完备的生命周期管理功能 (`resolving` 和 `afterResolving`)**。

只要在 `Grid::make()` 与 `Form::make()` 中通过容器进行实例构建：
```php
$grid = app(static::class, ['repository' => $repository]);
```
整个生命周期立即与 Laravel 容器无缝融合，由 Laravel 框架原生负责维护和派发，完全无需自定义上下文存储。

#### 3.3.2 开发者体验 (DX)：Fluent 静态方法与容器的双向对齐

在 `Grid` 和 `Form` 中提供优雅的静态钩子，底层直接绑定到 Laravel 容器：

```php
namespace BlatUI\Admin;

use Closure;
use Illuminate\Container\Container;

class Grid
{
    /**
     * 注册 Grid 构建前置钩子 (依赖注入容器原生 resolving 回调)
     */
    public static function resolving(Closure $callback): void
    {
        Container::getInstance()->resolving(static::class, function ($instance) use ($callback) {
            $callback($instance);
        });
    }

    /**
     * 注册 Grid 构建后置钩子 (依赖注入容器原生 afterResolving 回调)
     */
    public static function resolved(Closure $callback): void
    {
        Container::getInstance()->afterResolving(static::class, function ($instance) use ($callback) {
            $callback($instance);
        });
    }

    /**
     * 容器化实例化工厂方法
     */
    public static function make(mixed $repository = null, ?Closure $callback = null): static
    {
        // 1. 通过容器生成实例，自动触发所有容器注册的 resolving 回调
        /** @var static $instance */
        $instance = Container::getInstance()->make(static::class, ['repository' => $repository]);

        // 2. 执行开发者业务定义的构建闭包
        if ($callback instanceof Closure) {
            $callback($instance);
        }

        return $instance;
    }
}
```

同理，`Form` 采用完全一致的容器钩子实现：
- `Form::resolving(Closure $callback)`：绑定至 `Container::resolving(Form::class, ...)`；
- `Form::resolved(Closure $callback)`：绑定至 `Container::afterResolving(Form::class, ...)`；
- `Form::make()`：通过 `Container::make(static::class, ...)` 构造。

#### 3.3.3 在 ServiceProvider 中的标准扩展方式
开发者或扩展包作者既可以使用静态语法糖，也可以直接利用原生 ServiceProvider 依赖注入：
```php
public function boot(): void
{
    // 方式 A：经典 Dcat 风格肌肉记忆
    Grid::resolving(function (Grid $grid) {
        $grid->paginate(15);
    });

    // 方式 B：现代 Laravel 容器原生风格
    $this->app->resolving(Grid::class, function (Grid $grid) {
        $grid->paginate(15);
    });
}
```

---

## 4. Integration Points & Call Sites

1. **中间件层**：
   - `BlatUI\Admin\Http\Middleware\Permission`：
     - 将原本硬编码的 `abort(403, ...)` 替换为 `throw new PermissionDeniedException($message);`。
     - 触发前记录 `Admin::logger()->warning('Admin access denied', [...]);`。
2. **控制器层 (`ResourceController` & `AuthController`)**：
   - `AuthController`：利用原生 Guard 触发 Laravel 标准 Auth 事件，并在关键操作输出诊断日志。
3. **DSL 引擎层 (`Grid` & `Form`)**：
   - 将 `Grid::make()` 与 `Form::make()` 改为通过 `Container::getInstance()->make()` 构建。
   - 暴露 `resolving()` 与 `resolved()` 静态门面方法。

---

## 5. Explicitly Out of Scope & Rejected Decisions

1. **坚决拒绝自制 Context 数组字典 (Rejected Custom Context Dictionaries)**：
   - Dcat 在 `Admin::context()` 中手工维护回调数组是历史时代的权宜之计。现代 Laravel 容器已原生提供 `resolving` 与 `afterResolving` 机制，重复实现 Context 字典属于画蛇添足。
2. **拒绝空造冗余的事件类 (Rejected Redundant Event Classes)**：
   - 认证事件交给 Laravel 官方 `Illuminate\Auth\Events\*`；
   - 模型增删改事件交给 Eloquent 官方 `Model Events` / `Observers`；
   - UI 构建钩子交给 Laravel `Service Container`。不凭空创造不需要入队列、无广播需求的空壳事件类。
3. **拒绝接管宿主全局 `Handler`**：
   - 充分利用 Laravel 原生 Exception 自渲染（`render()` 方法）能力，使扩展包自洽、无侵入。
4. **拒绝使用后端 PHP 拼接 HTML 渲染错误页**：
   - 错误视图必须采用标准的 Blade 模板与 `{{ }}` 原生转义，禁止 Heredoc 与字符串拼接。
5. **区分系统日志与操作审计**：
   - `Logger` 负责系统诊断与异常追踪（文件/标准输出），不与数据库 `OperationLog` 混淆。

---

## 6. Test Plan

必须保持 Pest 4/5 + Orchestra Testbench 100% 覆盖：

| 测试文件 | 验证范围 |
| :--- | :--- |
| `tests/Feature/Exceptions/AdminExceptionTest.php` | 验证 `PermissionDeniedException`、`ResourceNotFoundException` 的 JSON 响应（状态码、结构）与 Web 错误页面渲染（视图标题、布局无裸露） |
| `tests/Feature/Support/LoggerTest.php` | 验证 `Admin::logger()` 是否正确继承配置通道，日志是否自动附带 `admin_user_id` 等上下文信息 |
| `tests/Feature/Grid/GridLifecycleTest.php` | 验证通过 `Grid::resolving()` 和 `app()->resolving(Grid::class)` 成功在构建前后拦截并注入配置 |
| `tests/Feature/Form/FormLifecycleTest.php` | 验证通过 `Form::resolving()` 和 `app()->resolving(Form::class)` 成功在构建前后拦截并注入配置 |

---

## 7. Verification Gate

```bash
composer test                 # PHPStan 0 errors, Pint passed, Type Coverage 100%, Pest all green
grep -rn '{!!' resources/views # 必须输出为空 (零非转义 Blade 输出)
```
