# Task List: BlatUI Admin Lifecycle & Dependency Management Engine

- [x] **Phase 1: Diagnostics (预检与体检报告体系)**
  - [x] 1.1 创建 `CheckStatus` 枚举 (`PASS`, `WARN`, `FAIL` 及标签/图标)
  - [x] 1.2 创建 `CheckResult` 不可变 DTO (`name`, `status`, `message`, `suggestedFix`)
  - [x] 1.3 创建 `DiagnosticReport` 模型 (结果聚合、计数、表格行转换 `toTableRows()`)
  - [x] 1.4 创建 `EnvironmentDetector` 服务 (Composer/Node/PM/Livewire/Flux/Tailwind/PeerPackages 探测)
  - [x] 1.5 编写并通过 Phase 1 单元测试 (`CheckStatusTest`, `CheckResultTest`, `DiagnosticReportTest`, `EnvironmentDetectorTest`)

- [x] **Phase 2: Actions (无状态执行工具箱)**
  - [x] 2.1 创建 `ProcessRunner` (封装 npm/pnpm/yarn/bun/npx/composer 执行与超时隔离)
  - [x] 2.2 创建 `ViteWiringManager` (实现 Tailwind v4 `@import` 追加与 3 种 JS 环境防 Duplicate-Alpine 插桩)
  - [x] 2.3 创建 `ArtisanRunner` (安全执行内部 Artisan 命令如 publish/migrate/blatui:add)
  - [x] 2.4 创建 `StubManager` (模板分发与已有组件防覆盖检查)
  - [x] 2.5 编写并通过 Phase 2 单元测试 (`ProcessRunnerTest`, `ViteWiringManagerTest`, `ArtisanRunnerTest`, `StubManagerTest`)

- [ ] **Phase 3: Wizard (交互向导与编排)**
  - [ ] 3.1 创建 `InstallContext` DTO (封装 dryRun, force, fresh, interactive, apexcharts, components 偏好)
  - [ ] 3.2 创建 `StepResult` DTO (封装步骤成功/失败/计划操作输出)
  - [ ] 3.3 创建 `InstallWizard` (纯 `laravel/prompts` 驱动：渲染表格、问询偏好、spin 执行 Actions、outro 总结)
  - [ ] 3.4 编写并通过 Phase 3 单元测试 (`InstallContextTest`, `InstallWizardTest`)

- [ ] **Phase 4: CLI Commands (控制台接入点)**
  - [ ] 4.1 创建 `BlatuiAdminCommand` 主命令 (`php artisan blatui:admin {--install}`)
  - [ ] 4.2 重构 `AdminInstallCommand` 为代理别名薄层 (`php artisan admin:install`)
  - [ ] 4.3 在 `AdminServiceProvider` 中注册新命令与服务绑定
  - [ ] 4.4 编写并通过 Feature 测试 (`BlatuiAdminCommandTest`, `AdminInstallCommandTest`)

- [ ] **Phase 5: Cleanup & Verification (清理与全量门禁)**
  - [ ] 5.1 移除废弃旧文件 (`InstallStep.php`, `InteractsWithEnvironment.php`, 旧 `EnvironmentDetectionTest.php`)
  - [ ] 5.2 运行 Pint 格式化与检查 (`composer lint`)
  - [ ] 5.3 运行 PHPStan 静态分析 (`composer analyse`)
  - [ ] 5.4 验证 100% Type Coverage (`composer test:types`)
  - [ ] 5.5 运行全量 Pest 测试套件 (`composer test:unit`)
  - [ ] 5.6 运行完整 CI 门禁 (`composer test`)
