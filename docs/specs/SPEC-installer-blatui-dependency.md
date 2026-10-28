# Spec: Host Project BlatUI Core Package Installation on `admin:install`

## 1. Objective & Architectural Rationale

### 为什么必须让宿主项目直接依赖 `anousss007/blatui`？
在以往的安装流程中，当宿主项目安装 `x-multibyte/blatui-admin` 时，`anousss007/blatui` 仅作为间接（传递性）依赖存在于 `composer.lock` 中，宿主项目的 `composer.json` 并未直接声明它。

让宿主项目在执行 `admin:install`（或 `blatui:admin --install`）时**直接依赖 `anousss007/blatui`**，核心目的不是要在本项目中去除它，而是为架构“松绑”，彻底解开多项为兼容间接依赖而量身打造的“枷锁”：

1. **确立宿主项目的第一公民地位（First-Class Ownership）**：
   - 宿主项目直接声明 `anousss007/blatui` 后，即正式成为一个 BlatUI 应用。宿主开发者可以直接运行 `php artisan blatui:add`，在业务控制器和全站 Blade 视图中无缝使用 `<x-ui.*>` 组件，而无需依赖管理后台包作为中介代理。
2. **彻底解决 AI 上下文与技能发现（Laravel Boost Invariant）**：
   - 依据 ADR-010，`laravel/boost` 只会发现宿主项目的**直接依赖**（`$package->isDirect()`）。当宿主显式依赖 `anousss007/blatui` 后，AI Coding Assistant（如 Claude Code、Cursor）能够原生获得官方的 `blatui-development` 组件技能和 `blatui-admin-development` 管理后台技能，免去了在 `blatui-admin` 中重复搬运、二次封装或维护同步补丁的繁琐包袱。
3. **消除版本与工具链的维护中介层**：
   - 宿主项目可以根据自身需求独立升级或维护 `anousss007/blatui`，`blatui-admin` 专注于管理后台的生命周期编排与管理功能，不再充当底层 UI 库的“保姆式代理”。

---

## 2. Acceptance Criteria

- 当执行 `php artisan admin:install` 或 `php artisan blatui:admin --install` 时，安装引擎检查宿主项目的 `composer.json`；
- 若宿主项目的 `composer.json` 中尚未直接声明 `anousss007/blatui`，安装引擎将自动执行 `composer require anousss007/blatui:^1.36 -W`，将其作为直接依赖写入宿主项目的 `composer.json`；
- 在 `--dry-run` 空跑模式下，输出的安装计划明确标示出将要执行的 `composer require anousss007/blatui:^1.36 -W`；
- 如果宿主项目已经显式声明了该依赖，则跳过此步，避免重复请求网络或修改锁文件；
- 本项目（`x-multibyte/blatui-admin`）自身的 `composer.json` 保持不变，正常依赖 `anousss007/blatui`，两者互不冲突。

---

## 3. Tech Stack

- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0`
- **Composer Runtime API**: `^2.2`
- **Console UI**: `laravel/prompts`
- **Target Package Constraint**: `anousss007/blatui:^1.36`
- **Test Framework**: Pest 4/5, Orchestra Testbench 10/11

---

## 4. Commands

```bash
# 全套测试与质量门禁验证
composer test

# 单元测试
composer test:unit

# 静态类型与规范分析
composer analyse
composer test:types
composer lint:check

# 验证安装命令演练
php artisan admin:install --dry-run
php artisan blatui:admin --install --dry-run
```

---

## 5. Project Structure & Affected Modules

```
src/
├── Console/
│   ├── Commands/
│   │   ├── AdminInstallCommand.php         # 保持纯净代理，将参数统一转发至 InstallWizard
│   │   └── BlatuiAdminCommand.php          # 规范入口命令，转发至 InstallWizard
│   └── Installer/
│       ├── Diagnostics/
│       │   └── EnvironmentDetector.php     # 增加 hasDirectComposerPackage()，精准检测宿主 composer.json
│       └── Wizard/
│           └── InstallWizard.php           # 确保 requiredComposerPackages() 按需包含 anousss007/blatui
docs/
└── decisions/
    └── ADR-011-direct-blatui-dependency-in-host-application.md # 记录此次解绑决策与动机
tests/
├── Unit/Installer/Diagnostics/EnvironmentDetectorTest.php
├── Unit/Installer/Wizard/InstallWizardTest.php
└── Feature/Commands/AdminInstallCommandTest.php
```

---

## 6. Code Style & Architecture

遵循现有四层生命周期架构：
- **Diagnostics (`EnvironmentDetector`)**: 增加 `hasDirectComposerPackage(string $package): bool`，只探测宿主根目录 `composer.json` 中的 `require` / `require-dev`，不因 `composer.lock` 或 vendor 传递依赖而发生误判。
- **Wizard (`InstallWizard`)**: 在 `requiredComposerPackages()` 中，针对 `anousss007/blatui` 使用 `hasDirectComposerPackage` 判定。如果未直接引入，则加入安装清单。
- **Actions (`ProcessRunner`)**: 继续使用 `-W` (`--with-all-dependencies`) 参数执行 Composer，确保依赖版本安全协调。

```php
/**
 * 确定宿主项目 composer.json 中是否已直接声明指定包
 */
public function hasDirectComposerPackage(string $package): bool
{
    $jsonPath = $this->basePath('composer.json');

    if (! is_readable($jsonPath)) {
        return false;
    }

    /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $json */
    $json = json_decode((string) file_get_contents($jsonPath), true) ?: [];

    return isset($json['require'][$package]) || isset($json['require-dev'][$package]);
}
```

---

## 7. Testing Strategy

1. **`EnvironmentDetectorTest`**:
   - 当 `composer.lock` 存在 `anousss007/blatui` 但宿主 `composer.json` 不存在时，`hasDirectComposerPackage('anousss007/blatui')` 返回 `false`。
   - 当写入宿主 `composer.json` 后，返回 `true`。
2. **`InstallWizardTest`**:
   - 当宿主缺少直接声明时，`requiredComposerPackages()` 必须包含 `anousss007/blatui:^1.36`。
   - `--dry-run` 计划中必须打印出安装 `anousss007/blatui:^1.36`。
   - 完整管道执行时，`ProcessRunner` 必须调用包含 `anousss007/blatui:^1.36` 的 composer 命令。
3. **`AdminInstallCommandTest`**:
   - 验证别名命令 `admin:install` 完整传递上下文并触发上述流程。

---

## 8. Boundaries

- **Always**:
  - 全流程必须通过 `composer test`（Larastan Level 7, Pint, 100% Type Coverage, Pest）。
  - 执行 `composer require` 必须附带 `-W`，以防止潜在的传递依赖版本冲突。
- **Never**:
  - 绝不直接用正则或文件读写篡改宿主的 `composer.json` 或 `composer.lock`，必须完全委托给官方 Composer CLI 处理。
  - 绝不破坏 `blatui:admin --install` 与 `admin:install` 的现有参数与行为契约。

---

## 9. Status & Next Steps

本 Spec 已更新到位。在获得您的正式确认后，我们将进入 **Phase 2 (Plan & ADR-011)** 与 **Phase 3 (Tasks)** 阶段。
