# Spec: BlatUI Admin Lifecycle & Dependency Management Engine

## 1. Objective

`x-multibyte/blatui-admin` 是一个基于 **BlatUI (`anousss007/blatui`)** 的 Laravel 扩展包。
本项目的核心职责是**作为 BlatUI 及其 Admin 架构在 Laravel 宿主项目中的“生命周期与依赖管理引擎”**。

它负责协调并解决 BlatUI 所需的复杂跨技术栈依赖链：
- **PHP / Laravel 依赖**：Livewire、Blade 图标库、Tailwind-Merge 等 Peer 包。
- **Node.js / 前端工具链**：Node 18+ 环境探测、包管理器自动识别（npm / pnpm / yarn / bun / npx）、Alpine.js 插件与 ApexCharts 等前端包安装。
- **构建插桩 (Vite)**：在宿主 `resources/css/app.css` 与 `resources/js/app.js` 中精准注入 BlatUI 样式与 Alpine/Livewire 运行时。
- **文件与资源管理**：配置、视图、数据迁移发布与模板代码生成（Stub Generation）。

目标用户是希望在任何现代 Laravel 项目中无缝引入 BlatUI 管理后台的开发者。

---

## 2. Capability Map (能力地图与构建顺序)

整个依赖管理生命周期由四个解耦的高内聚模块构成：

| Module ID | 职责 (Responsibility) | 依赖模块 (Depends on) | 核心行为 |
|---|---|---|---|
| `diagnostics` | 运行前自检体系 | — | 探测 PHP/Composer/Node/PM/Tailwind/Livewire，生成 `DiagnosticReport` 并输出结构化状态表格。 |
| `actions` | 执行与文件操作工具箱 | — | 封装 `Process` (npm/pnpm/yarn/bun/npx/composer)、`ViteWiring`、`StubManager`、`ArtisanRunner`。 |
| `wizard` | Prompts 分步引导向导 | `diagnostics`, `actions` | 基于 `laravel/prompts` 收集用户配置、确认阻断项、调度 Actions 并显示 `spin()` 进度。 |
| `cli` | 控制台命令接入点 | `wizard` | 注册 `php artisan blatui:admin --install`，提供 `admin:install` 别名向后兼容。 |

**Build Order:** `diagnostics` → `actions` → `wizard` → `cli`

---

## 3. Tech Stack

- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0` (`illuminate/support`, `illuminate/console`, `illuminate/process`)
- **Core Package**: `anousss007/blatui` (`^1.36`)
- **CLI Interaction**: `laravel/prompts` (`^0.3 || ^0.4`)
- **Process Component**: Laravel `Process` Facade (wrapping `symfony/process`)
- **Testing**: Pest `^4.6 || ^5.0`, Orchestra Testbench `^10.0 || ^11.0`, Pest Type Coverage (`--min=100`)
- **Static Analysis**: PHPStan / Larastan (Level 7)
- **Code Style**: Laravel Pint (`preset: laravel`)

---

## 4. Commands

```bash
# 开发与测试环境
Build Assets:      composer build        # vendor/bin/testbench workbench:build --ansi
Serve Workbench:   composer serve        # vendor/bin/testbench serve --ansi
Run Tests:         composer test:unit    # vendor/bin/pest --parallel
Type Coverage:     composer test:types   # vendor/bin/pest --type-coverage --min=100
Code Style Check:  composer lint:check   # vendor/bin/pint --parallel --test
Code Style Fix:    composer lint         # vendor/bin/pint --parallel
Static Analysis:   composer analyse      # vendor/bin/phpstan analyse
Full CI Pipeline:  composer test         # analyse + lint:check + test:types + test:unit

# 扩展包对外提供的控制台命令
Primary Install:   php artisan blatui:admin --install
Dry Run Plan:      php artisan blatui:admin --install --dry-run
Force Reinstall:   php artisan blatui:admin --install --force
Fresh Migration:   php artisan blatui:admin --install --fresh
Legacy Alias:      php artisan admin:install [--dry-run] [--force] [--fresh]
```

---

## 5. Project Structure

```
src/
├── Admin.php                              # Admin 核心管理类
├── AdminServiceProvider.php               # 服务提供者 (配置合并、命令与组件绑定)
├── Facades/
│   └── Admin.php                          # 门面类
├── Console/
│   ├── Commands/
│   │   ├── AdminCommand.php               # 占位/辅助命令
│   │   ├── AdminInstallCommand.php        # 兼容旧命令签名的别名薄层
│   │   └── BlatuiAdminCommand.php         # 主命令: blatui:admin {--install}
│   └── Installer/
│       ├── Diagnostics/                   # 预检体系
│       │   ├── CheckStatus.php            # PASS | WARN | FAIL 状态枚举
│       │   ├── CheckResult.php            # 单项检测数据对象 (name, status, message, fix)
│       │   ├── DiagnosticReport.php       # 检测报告模型 (聚合结果并生成表格行)
│       │   └── EnvironmentDetector.php    # 实际运行环境探测器 (PHP/Node/PM/Lock/Tailwind)
│       ├── Actions/                       # 执行工具箱 (无状态独立操作)
│       │   ├── ProcessRunner.php          # 封装 npm/pnpm/yarn/bun/npx/composer 调用
│       │   ├── ViteWiringManager.php      # 前端入口精准注入 (app.css / app.js)
│       │   ├── StubManager.php            # 模板生成与文件发布替换
│       │   └── ArtisanRunner.php          # 内部 Artisan 子命令安全触发 (vendor:publish/migrate)
│       └── Wizard/                        # 分步引导与调度
│           ├── InstallContext.php         # 运行选项 DTO (dryRun, force, fresh, interactive, etc.)
│           ├── StepResult.php             # 步骤执行结果封装
│           └── InstallWizard.php          # 基于 laravel/prompts 的分步引导流程
tests/
├── ArchTest.php                           # 架构与安全测试 (strict_types, 禁用 dd/env)
├── TestCase.php                           # Testbench 测试基类
├── Unit/
│   └── Installer/
│       ├── Diagnostics/                   # 预检测试
│       ├── Actions/                       # 执行工具箱测试
│       └── Wizard/                        # 向导交互与上下文测试
└── Feature/
    └── Commands/                          # 控制台命令功能测试
```

---

## 6. Code Style & Standards

1. **Strict Types**: 每个 PHP 文件头必须为 `declare(strict_types=1);`。
2. **Type Safety**: 所有类属性、方法参数、返回类型均需显式声明，保持 100% Type Coverage。
3. **No Global State Leaks**: 避免在服务容器中注册易发生时序死锁的隐式状态；依赖优先通过构造函数显式传递。
4. **Pure Prompts UI**: 控制台交互与输出统一通过 `laravel/prompts`（`table`, `intro`, `select`, `multiselect`, `confirm`, `spin`, `error`, `note`, `outro`）完成，禁止混用 `Command::$components` 造成双重通道。

### 代码规范范例

```php
<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Diagnostics;

final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public string $message,
        public ?string $suggestedFix = null,
    ) {}

    public function isFailure(): bool
    {
        return $this->status === CheckStatus::FAIL;
    }
}
```

---

## 7. Testing Strategy

1. **框架与环境**：使用 Pest PHP 配合 Orchestra Testbench，以隔离环境测试 Laravel 扩展包行为。
2. **Process 隔离与安全**：针对调用外部工具（composer, npm, pnpm）的测试，利用 `Process::fake()` 避免在测试机实际执行构建或全局安装。
3. **Mock-free 纯逻辑测试**：`DiagnosticReport`、`CheckResult`、`InstallContext` 等模型采用纯数据驱动测试，避免深层 Mockery。
4. **集成测试**：通过 `$this->artisan('blatui:admin --install --dry-run')` 验证全流程在非破坏性模式下的输出与退出码。

---

## 8. Boundaries

### Always Do (必须遵循)
- 运行 `composer test` 确认全套检查通过（Pint、PHPStan Level 7、Pest Type Coverage 100%、Pest Unit）。
- 在检测到阻断级错误（如 Node.js 缺失或版本 < 18）时，给出明确的 `suggestedFix` 并中止安装。
- 在修改任何文件前支持 `--dry-run` 模式，完整输出预检报告与计划执行的操作。
- 保证 `resources/css/app.css` 和 `resources/js/app.js` 的 Vite 插桩具备幂等性（重复运行不会重复追加引入）。

### Ask First (需确认)
- 更改向后兼容命令 `admin:install` 的外部参数签名。
- 引入新的第三方 Composer 运行时生产依赖（目前仅限 `anousss007/blatui` 及 `illuminate/*`）。
- 更改安装流程中必需组件与可选组件的默认勾选策略。

### Never Do (坚决禁止)
- 在生产代码（`src/`）中使用 `env()`，必须使用 `config()`（ArchTest 严查）。
- 在生产代码中残留 `dd()`, `ddd()`, `dump()`, `exit()`。
- 强行覆盖用户已存在的同名业务文件而不提供 `--force` 守卫或确认提示。
- 编写无任何调用方和扩展注册缝隙的假抽象接口。

---

## 9. Success Criteria (验收标准)

1. [ ] 执行 `php artisan blatui:admin --install --dry-run` 能输出漂亮的 Prompts 自检表格及安装计划，退出码为 0。
2. [ ] 执行 `php artisan admin:install --dry-run` 能够作为别名正常运行，保证既有 CI 与文档兼容。
3. [ ] 运行前自检能够准确识别：
   - PHP 版本（>= 8.3 为 PASS，否则 FAIL）
   - Composer 二进制是否存在
   - Node.js 版本（>= 18 为 PASS，未安装或 < 18 为 FAIL）
   - 项目 Node 包管理器（优先根据 lockfile 识别 bun/pnpm/yarn/npm）
   - Tailwind CSS 版本状态
   - Livewire 安装状态
4. [ ] 废弃的旧死代码（`InstallStep.php`, `InteractsWithEnvironment.php`, 旧 11 个 steps 散件）彻底清空。
5. [ ] `composer test` 四维质量门禁全部通过（Pint 格式化、PHPStan Level 7 零错误、Type Coverage 100%、Pest 单元测试全绿）。
