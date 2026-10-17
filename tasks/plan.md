# BlatUI Admin 安装器三核解耦架构重构实施计划

> **Goal**: 依据 `SPEC.md` 将 `x-multibyte/blatui-admin` 的安装器从单体过程脚本重构成高内聚、易测试、具备宿主自适应能力的四层引擎（Diagnostics、Actions、Wizard、CLI）。

---

## 1. Context (背景与上下文)

当前 `src/Console/Commands/AdminInstallCommand.php` 存在明显的“单体脚本”缺陷：
1. **预检与状态混杂**：环境检测散落在 `InteractsWithEnvironment` trait 与命令闭包中，无法生成结构化的体检报告模型，无法为 `--dry-run` 和交互模式提供清晰的检测表格。
2. **执行缺乏抽象**：文件读写 (`file_put_contents`)、进程执行 (`Process::run`)、子命令触发直接混在安装步骤中，难以进行单独的单元测试。
3. **输出 API 混乱**：混用了 `Command::$components` 与 `laravel/prompts`，违背了“单一控制台交互通道”的设计原则。
4. **宿主自适应保护不够完备**：对已有 Livewire/Flux（防止 Duplicate-Alpine 运行时冲突）、Tailwind v3 vs v4（增量追加而非覆盖）、按需引入 ApexCharts 以及 BlatUI 组件代码拥有机制（`blatui:add`）的感知与防呆逻辑需要系统化。

本实施计划将整个依赖管理与安装生命周期严格按照构建顺序进行解耦落地：
`diagnostics` (预检体系) → `actions` (执行工具箱) → `wizard` (引导向导) → `cli` (控制台入口) → `cleanup` (死代码清理与全量门禁)。

---

## 2. 架构设计与模块依赖关系 (Architecture & Dependencies)

```
┌─────────────────────────────────────────────────────────────┐
│                    CLI Commands Layer                       │
│  - BlatuiAdminCommand (`php artisan blatui:admin --install`) │
│  - AdminInstallCommand (`php artisan admin:install` 兼容别名) │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                       Wizard Layer                          │
│  - InstallContext (运行参数与用户偏好 DTO)                   │
│  - StepResult (单步执行状态封装)                            │
│  - InstallWizard (纯 laravel/prompts 交互与编排中心)        │
└──────────────┬──────────────────────────────┬───────────────┘
               │                              │
               ▼                              ▼
┌──────────────────────────────┐ ┌────────────────────────────┐
│      Diagnostics Layer       │ │       Actions Layer        │
│  - CheckStatus (枚举)        │ │  - ProcessRunner (进程执行) │
│  - CheckResult (单项 DTO)    │ │  - ViteWiringManager (插桩)│
│  - DiagnosticReport (报告模型)│ │  - StubManager (文件发布)  │
│  - EnvironmentDetector (探测)│ │  - ArtisanRunner (命令触发)│
└──────────────────────────────┘ └────────────────────────────┘
```

**依赖单向流向**：
- `Diagnostics` 与 `Actions` 互不依赖，为独立可测的基础设施。
- `Wizard` 依赖 `Diagnostics`（只读体检）与 `Actions`（副作用调度）。
- `CLI` 仅作为薄入口，构建 `InstallContext` 并委派给 `InstallWizard`。

---

## 3. 详细实施任务拆解 (Phased Tasks)

### Phase 1: Diagnostics 预检与体检报告体系
**核心职责**：环境探测、宿主依赖检测、结构化自检模型生成（只读、无副作用）。

1. **`CheckStatus.php`** (`src/Console/Installer/Diagnostics/CheckStatus.php`):
   - PHP 8.3 纯枚举：`case PASS; case WARN; case FAIL;`
   - 方法：`label(): string`，`icon(): string`（用于终端表格渲染的标识符）。
2. **`CheckResult.php`** (`src/Console/Installer/Diagnostics/CheckResult.php`):
   - 强类型不可变 DTO：`public readonly string $name`, `public readonly CheckStatus $status`, `public readonly string $message`, `public readonly ?string $suggestedFix = null`。
3. **`DiagnosticReport.php`** (`src/Console/Installer/Diagnostics/DiagnosticReport.php`):
   - 报告容器：保存 `array<int, CheckResult>`。
   - 方法：`add(CheckResult $result): self`, `hasCriticalFailures(): bool`, `hasWarnings(): bool`, `toTableRows(): array<int, array<int, string>>`。
4. **`EnvironmentDetector.php`** (`src/Console/Installer/Diagnostics/EnvironmentDetector.php`):
   - 基于 `Composer\InstalledVersions` 探测 Composer 依赖（`livewire/livewire`, `anousss007/blatui`, `gehrisandro/tailwind-merge-laravel`, `mallardduck/blade-lucide-icons`）。
   - 基于 `package.json` 探测 Node 依赖（`tailwindcss`, `alpinejs`, `@alpinejs/*`, `@floating-ui/dom`, `apexcharts`, `vite`）。
   - 探测系统环境与二进制：PHP 版本（>= 8.3）、Node.js 版本（>= 18）、Node 包管理器（检测 `bun.lockb/bun.lock`, `pnpm-lock.yaml`, `yarn.lock`, `package-lock.json` 或 `package.json` 的 `packageManager` 字段）。
   - 聚合方法：`fullDiagnostics(): DiagnosticReport`。
5. **单元测试与验证**：
   - `tests/Unit/Installer/Diagnostics/CheckStatusTest.php`
   - `tests/Unit/Installer/Diagnostics/CheckResultTest.php`
   - `tests/Unit/Installer/Diagnostics/DiagnosticReportTest.php`
   - `tests/Unit/Installer/Diagnostics/EnvironmentDetectorTest.php`
   - **验证命令**：`vendor/bin/pest tests/Unit/Installer/Diagnostics`

---

### Phase 2: Actions 无状态执行工具箱
**核心职责**：将文件 IO、进程执行、子命令调用封装为独立可测试的单元。

1. **`ProcessRunner.php`** (`src/Console/Installer/Actions/ProcessRunner.php`):
   - 封装 Laravel `Process` Facade，处理包管理器调用（`npm/pnpm/yarn/bun install/add` 与 `composer require`）。
   - 统一超时配置、环境变量隔离（`COMPOSER_MEMORY_LIMIT=-1`）、错误输出捕获。
2. **`ViteWiringManager.php`** (`src/Console/Installer/Actions/ViteWiringManager.php`):
   - **CSS 增量插桩**：检查 `resources/css/app.css`，如果不存在则创建（含 `@import "tailwindcss";\n@import "./blatui.css";`）；若存在且缺少 `blatui.css` 则追加 `@import "./blatui.css";`。
   - **JS 智能插桩 (杜绝 Duplicate-Alpine)**：
     - 若宿主已有 Livewire 或 Flux：注入事件监听 `document.addEventListener('alpine:init', () => { registerBlatUI(window.Alpine) })` 并导入 `./blatui-core.js`。若有 Flux 则传参 `{ theme: false }`。
     - 若宿主无 Livewire 且无 Alpine：直接导入 `./blatui.js`。
     - 若宿主已有独立 Alpine（无 Livewire）：注册到现有 Alpine 实例。
   - 保证绝对幂等性，不重复注入。
3. **`ArtisanRunner.php`** (`src/Console/Installer/Actions/ArtisanRunner.php`):
   - 安全调用宿主内的 Artisan 命令（`vendor:publish --tag=blatui-foundations`, `vendor:publish --tag=blatui-admin`, `migrate`, `blatui:add` 等）。
   - 支持捕获输出并返回结构化执行状态。
4. **`StubManager.php`** (`src/Console/Installer/Actions/StubManager.php`):
   - 负责模板生成与文件发布，检测现有文件冲突并给出跳过/覆写策略。
5. **单元测试与验证**：
   - `tests/Unit/Installer/Actions/ProcessRunnerTest.php`
   - `tests/Unit/Installer/Actions/ViteWiringManagerTest.php`
   - `tests/Unit/Installer/Actions/ArtisanRunnerTest.php`
   - **验证命令**：`vendor/bin/pest tests/Unit/Installer/Actions`

---

### Phase 3: Wizard 引导向导与上下文编排
**核心职责**：使用 `laravel/prompts` 进行全流程交互与 Actions 编排。

1. **`InstallContext.php`** (`src/Console/Installer/Wizard/InstallContext.php`):
   - DTO 包含：`dryRun: bool`, `force: bool`, `fresh: bool`, `interactive: bool`, `composerPath: ?string`, `includeApexCharts: bool`, `selectedComponents: array<string>`。
2. **`StepResult.php`** (`src/Console/Installer/Wizard/StepResult.php`):
   - 封装每步执行结果：`success: bool`, `title: string`, `message: string`, `plannedAction: ?string`。
3. **`InstallWizard.php`** (`src/Console/Installer/Wizard/InstallWizard.php`):
   - 依赖注入 `EnvironmentDetector`, `ProcessRunner`, `ViteWiringManager`, `ArtisanRunner`。
   - 纯使用 `laravel/prompts`：
     - 使用 `table()` 展示预检表格。
     - 若有 `FAIL` 项，展示 `suggestedFix` 并使用 `warning()` 中止。
     - 若非 `--force` 且处于交互模式，使用 `confirm()` 确认继续。
     - 询问用户偏好（是否按需安装 `apexcharts`、需添加的核心 BlatUI 组件）。
     - `--dry-run` 模式：使用 `note()` 输出计划执行的各项操作，不产生实际文件与进程修改。
     - 执行模式：使用 `spin()` 串联执行各 Action，最终使用 `outro()` 输出完成提示。
4. **单元测试与验证**：
   - `tests/Unit/Installer/Wizard/InstallContextTest.php`
   - `tests/Unit/Installer/Wizard/InstallWizardTest.php`
   - **验证命令**：`vendor/bin/pest tests/Unit/Installer/Wizard`

---

### Phase 4: CLI 控制台接入点与向后兼容别名
**核心职责**：注册标准包命令与向后兼容别名。

1. **`BlatuiAdminCommand.php`** (`src/Console/Commands/BlatuiAdminCommand.php`):
   - 签名：`blatui:admin {--install} {--dry-run} {--fresh} {--force} {--composer-path=}`
   - 描述：`Manage BlatUI Admin lifecycle and dependencies.`
   - 逻辑：解析选项为 `InstallContext`，交由容器解析的 `InstallWizard::run()` 处理。
2. **`AdminInstallCommand.php`** (`src/Console/Commands/AdminInstallCommand.php`):
   - 签名：`admin:install {--dry-run} {--fresh} {--force} {--composer-path=}`
   - 改造为极简向后兼容别名薄层，直接调用 `InstallWizard`。
3. **`AdminServiceProvider.php`** (`src/AdminServiceProvider.php`):
   - 在 `$commands` 中注册 `BlatuiAdminCommand` 与 `AdminInstallCommand`。
   - 在服务容器中绑定 `EnvironmentDetector`、`ProcessRunner`、`ViteWiringManager`、`ArtisanRunner`、`InstallWizard`。
4. **功能测试与验证**：
   - `tests/Feature/Commands/BlatuiAdminCommandTest.php`：测试 `blatui:admin --install --dry-run` 退出码 0，测试 `--force` 行为。
   - `tests/Feature/Commands/AdminInstallCommandTest.php`：测试 `admin:install --dry-run` 别名兼容性。
   - **验证命令**：`vendor/bin/pest tests/Feature/Commands`

---

### Phase 5: 死代码清理与全量质量门禁 (Cleanup & Full Gates)
**核心职责**：清理废弃旧文件，确保代码库完全纯净并通过 CI 四维门禁。

1. **废弃文件清理**：
   - 移除 `src/Support/InstallStep.php`
   - 移除 `src/Console/Commands/Concerns/InteractsWithEnvironment.php`
   - 移除旧测试 `tests/Feature/Install/EnvironmentDetectionTest.php`（已被 `EnvironmentDetectorTest` 完全替代）。
2. **四维自动化门禁全量验证**：
   - `composer lint:check` (Laravel Pint 代码风格规范)
   - `composer analyse` (PHPStan Level 7 零警告、零报错)
   - `composer test:types` (Pest Type Coverage 100%)
   - `composer test:unit` (Pest 单元测试全绿)
   - `composer test` (全量 CI 脚本)

---

## 4. 全局设计硬约束 (Global Constraints)

1. **Strict Types & Type Safety**：所有新建 PHP 文件必须首行 `declare(strict_types=1);`，所有参数与返回类型必须显式声明，保持 100% Type Coverage。
2. **Pure Prompts Channel**：控制台交互统一通过 `laravel/prompts`（`table`, `confirm`, `note`, `warning`, `spin`, `outro`），严禁使用 `Command::$components`。
3. **Duplicate-Alpine 防御**：检测到宿主已有 Livewire 时，严禁安装 `alpinejs` npm 包，JS 必须采用 `alpine:init` 挂载 `blatui-core.js`。
4. **Additive CSS**：Tailwind v4 只追加 `@import "./blatui.css";`，绝不覆写宿主 `app.css`。
5. **No Dangerous Code**：严禁在 `src/` 中使用 `dd()`, `dump()`, `exit()`, `env()`（ArchTest 自动拦截）。

---

## 5. 验收标准与交付物 (Verification & Deliverables)

- [ ] `php artisan blatui:admin --install --dry-run` 成功输出结构化预检表格并打印安装计划，退出码为 0。
- [ ] `php artisan admin:install --dry-run` 别名等价执行，退出码为 0。
- [ ] `EnvironmentDetector` 准确识别全新项目、Livewire 项目、Flux 项目、Tailwind v3/v4 项目及不同包管理器 lockfile。
- [ ] `ViteWiringManager` 在三种 JS 环境和现有 CSS 场景下实现完全幂等的增量插桩。
- [ ] 旧散件代码完全清理，无死引用。
- [ ] `composer test` 四维门禁全部通过。
