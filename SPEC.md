# Spec: BlatUI Admin Lifecycle & Dependency Management Engine

## 1. Objective

`x-multibyte/blatui-admin` 是一个基于 **BlatUI (`anousss007/blatui`)** 的 Laravel 扩展包。
本项目的核心职责是**作为 BlatUI 及其 Admin 架构在 Laravel 宿主项目中的“生命周期与依赖管理引擎”**。

它负责协调并解决 BlatUI 所需的复杂跨技术栈依赖链：

- **PHP / Laravel 依赖探测与安装**：Livewire、Blade Lucide 图标库、Tailwind-Merge 等 Peer 包的按需检测与引入。
- **Node.js / 前端工具链自适应**：Node 18+ 环境探测、包管理器自动识别（npm / pnpm / yarn / bun / npx）、Alpine.js 插件与 ApexCharts 等前端包安装。
- **已有项目自适应插桩 (Existing Project Integration)**：非破坏性地在宿主 `resources/css/app.css` 与 `resources/js/app.js` 中接入 BlatUI 样式与运行时，**坚决避开 Duplicate-Alpine 陷阱与主题冲突**。
- **文件与组件管理**：通过 `blatui:add` 按需拉取 Admin 依赖的 UI 基础组件到 `resources/views/components/ui/`，发布配置与数据库迁移。

目标用户是在任何全新或**已有（Existing）**现代 Laravel 项目中无缝引入 BlatUI 管理后台的开发者。

---

## 2. 核心原则：已有项目自适应与非破坏性策略 (Adaptive & Non-Destructive)

由于扩展包是安装在用户的宿主项目中，宿主可能已经安装了部分或全部相关依赖。安装器必须遵循以下自适应矩阵：

### 2.1 CSS / Tailwind 适配规则
- **Tailwind v4+**：不得覆盖用户的 `app.css`，采用**追加导入**模式：在用户现有 CSS 之后追加 `@import "./blatui.css";`。
- **Tailwind v3**：自检标记为 `WARN`，并在自检报告中提示升级建议（`npx @tailwindcss/upgrade`），禁止强行破坏用户构建。

### 2.2 JS / Alpine / Livewire 运行时适配规则 (杜绝 Duplicate-Alpine)
- **全新项目（无 Alpine，无 Livewire）**：
  - 引入 `npm install -D alpinejs` 以及必须插件（`@alpinejs/anchor`, `@alpinejs/collapse`, `@alpinejs/focus`）。
  - `app.js` 注入：`import "./blatui.js";`。
- **已有 Livewire 或 Flux 的项目**：
  - **严禁执行 `npm install alpinejs`**（Livewire 已自带 Alpine，重复引入会导致致命的 duplicate-Alpine 冲突）。
  - 仅安装 Alpine 插件，不安装 `alpinejs` 核心包。
  - `app.js` 插桩模式必须切换为基于事件监听注入：
    ```javascript
    import { registerBlatUI } from './blatui-core.js'
    document.addEventListener('alpine:init', () => {
        registerBlatUI(window.Alpine) // 若检测到 Flux，传入 { theme: false }
    })
    ```
- **已有独立 Alpine（无 Livewire）的项目**：
  - 不得引入 `blatui.js`（会重复启动 Alpine），必须引入 `blatui-core.js` 并将插件注册挂载到用户现有的 Alpine 实例上。

### 2.3 按需图表依赖 (ApexCharts Opt-in)
- `apexcharts` 库体积较大（~150KB），**不得作为全局强制依赖**。
- 仅在用户勾选了仪表盘图表组件或通过交互选项启用时，才执行 `npm install -D apexcharts` 并调用 `php artisan vendor:publish --tag=blatui-charts`。

### 2.4 组件文件分发 (`blatui:add`)
- BlatUI 采用代码拥有模式，通过 `php artisan blatui:add <components>` 分发到 `resources/views/components/ui/`。
- 安装器需预定义 Admin 面板所需的核心组件集（例如 `button`, `card`, `input`, `dropdown`, `table` 等），在安装阶段按需拉取。
- 若目标目录已存在用户自定义组件，默认跳过或在交互提示下确认，防止无脑覆写。

---

## 3. Capability Map (能力地图与构建顺序)

整个依赖管理生命周期由四个解耦的高内聚模块构成：

| Module ID     | 职责 (Responsibility) | 依赖模块 (Depends on)        | 核心行为                                                                                                                    |
|---------------|---------------------|--------------------------|-------------------------------------------------------------------------------------------------------------------------|
| `diagnostics` | 运行前环境与依赖自检          | —                        | 探测宿主环境已装依赖（PHP/Node/PM/Livewire/Alpine/Flux/Tailwind/PeerPackages），生成包含状态标记与修复提示的 `DiagnosticReport` 表格。                     |
| `actions`     | 执行与文件操作工具箱          | —                        | 封装 `Process` (npm/pnpm/yarn/bun/npx/composer)、自适应 `ViteWiring` (区别全新/Livewire/已存在)、`StubManager`、`ArtisanRunner`。               |
| `wizard`      | Prompts 分步引导向导      | `diagnostics`, `actions` | 基于 `laravel/prompts` 呈现自检表格、询问安装偏好（可选图表/组件拉取）、按自适应规则调度 Actions 并以 `spin()` 呈现进度。                                         |
| `cli`         | 控制台命令接入点            | `wizard`                 | 注册 `php artisan blatui:admin --install`，提供 `admin:install` 别名向后兼容。                                                      |

**Build Order:** `diagnostics` → `actions` → `wizard` → `cli`

---

## 4. Tech Stack

- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0` (`illuminate/support`, `illuminate/console`, `illuminate/process`)
- **Core Package**: `anousss007/blatui` (`^1.36`)
- **CLI Interaction**: `laravel/prompts` (`^0.3 || ^0.4`)
- **Process Component**: Laravel `Process` Facade (wrapping `symfony/process`)
- **Testing**: Pest `^4.6 || ^5.0`, Orchestra Testbench `^10.0 || ^11.0`, Pest Type Coverage (`--min=100`)
- **Static Analysis**: PHPStan / Larastan (Level 7)
- **Code Style**: Laravel Pint (`preset: laravel`)

---

## 5. Commands

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

## 6. Project Structure

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
│       ├── Diagnostics/                   # 预检体系 (感知宿主既有环境)
│       │   ├── CheckStatus.php            # PASS | WARN | FAIL 状态枚举
│       │   ├── CheckResult.php            # 单项检测数据对象 (name, status, message, fix)
│       │   ├── DiagnosticReport.php       # 检测报告模型 (聚合结果并生成表格行)
│       │   └── EnvironmentDetector.php    # 宿主深度探测 (PHP/Node/PM/Livewire/Flux/Tailwind/已装依赖)
│       ├── Actions/                       # 执行工具箱 (无状态独立操作)
│       │   ├── ProcessRunner.php          # 封装 npm/pnpm/yarn/bun/npx/composer 调用
│       │   ├── ViteWiringManager.php      # 前端入口智能插桩 (区分全新/已装Alpine/Livewire/Flux)
│       │   ├── StubManager.php            # 模板生成与文件发布替换
│       │   └── ArtisanRunner.php          # 内部 Artisan 子命令安全触发 (vendor:publish/migrate/blatui:add)
│       └── Wizard/                        # 分步引导与调度
│           ├── InstallContext.php         # 运行选项 DTO (dryRun, force, fresh, interactive, etc.)
│           ├── StepResult.php             # 步骤执行结果封装
│           └── InstallWizard.php          # 基于 laravel/prompts 的分步引导流程
tests/
├── ArchTest.php                           # 架构与安全测试 (strict_types, 禁用 dd/env)
├── TestCase.php                           # Testbench 测试基类
├── Unit/
│   └── Installer/
│       ├── Diagnostics/                   # 预检测试 (覆盖各种宿主已有依赖场景)
│       ├── Actions/                       # 执行工具箱测试
│       └── Wizard/                        # 向导交互与上下文测试
└── Feature/
    └── Commands/                          # 控制台命令功能测试
```

---

## 7. Code Style & Standards

1. **Strict Types**: 每个 PHP 文件头必须为 `declare(strict_types=1);`。
2. **Type Safety**: 所有类属性、方法参数、返回类型均需显式声明，保持 100% Type Coverage。
3. **Additive Operations**: 对用户现有文件的修改必须是增量式（Additive），不得无提示覆盖用户的既有代码。
4. **Pure Prompts UI**: 控制台交互与输出统一通过 `laravel/prompts`，杜绝与 `Command::$components` 混用。

---

## 8. Boundaries

### Always Do (必须遵循)

- 在宿主已存在 Livewire 时，**绝对禁止**安装 npm 包 `alpinejs`。
- 修改 `app.css` / `app.js` 时必须先检查内容，确保已包含目标语句时不重复插入（保证幂等性）。
- 在检测到阻断级错误（如 Node.js 缺失或版本 < 18）时，给出明确的 `suggestedFix` 并中止安装。
- 支持 `--dry-run` 模式，完整输出预检报告与计划执行的操作。

### Ask First (需确认)

- 覆盖用户在 `resources/views/components/ui/` 中已存在的同名组件。
- 更改向后兼容命令 `admin:install` 的外部参数签名。
- 默认勾选安装重量级可选包（如 `apexcharts`）。

### Never Do (坚决禁止)

- 直接覆盖替换宿主的 `resources/css/app.css` 或 `resources/js/app.js`。
- 在生产代码中残留 `dd()`, `ddd()`, `dump()`, `exit()`。
- 在生产代码（`src/`）中使用 `env()`（ArchTest 严查）。
- 忽视宿主现有 Livewire/Flux 环境而强推独立的 Alpine 运行时。

---

## 9. Success Criteria (验收标准)

1. [ ] 执行 `php artisan blatui:admin --install --dry-run` 能准确根据宿主已有依赖输出自检表格与增量适配计划，退出码为 0。
2. [ ] 执行 `php artisan admin:install --dry-run` 能够作为别名正常运行。
3. [ ] 运行前自检能够准确识别并正确分类各种已有项目状态：
    - 已有 Livewire -> 标记避免安装 npm `alpinejs`
    - 已有 Tailwind v4 -> 标记 CSS 增量注入
    - 已有 Tailwind v3 -> 给出升级建议警告
    - 识别存在的 lockfile（bun/pnpm/yarn/npm）
4. [ ] 废弃的旧死代码（`InstallStep.php`, `InteractsWithEnvironment.php`, 旧 11 个 steps 散件）彻底清空。
5. [ ] `composer test` 四维质量门禁全部通过（Pint 格式化、PHPStan Level 7 零错误、Type Coverage 100%、Pest 单元测试全绿）。
