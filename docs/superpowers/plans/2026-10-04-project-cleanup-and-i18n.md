# Project Cleanup & i18n Completion Implementation Plan

> **Status: COMPLETE (verified 2026-10-04).** All three tasks landed as four commits on `main`: `1be45ed` (workspace cleanup), `0e231b1` (i18n), `7dc2299` (UI smoke-test skill), `470ee3b` (docs and version display). Final gate output: PHPStan level 7 with 0 errors, Pint clean, type coverage 100.0%, and 214 Pest tests passing with 1071 assertions in 11.2s. `grep -rn '{!!' resources/views` returns zero matches; `git status --porcelain` now reports only the user-owned `opencode.json`.
>
> Two plan assumptions were corrected against real output during execution rather than papered over: the zh_CN translation defect is a silent English fallback, not a leaked key name (see Review Focus item 2), and Pint enforces `single_blank_line_at_eof` on files under `lang/` (see Task 2 Step 4).

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 清理工作区遗留物、补齐语言包对称性、让硬编码文案走翻译通道，并把文档状态头修正为与代码一致。

**Architecture:** 三组彼此独立的修补，每组自带可验证的测试闭环，不引入新的架构层。翻译修补遵循 Laravel 原生 `__()` 命名空间键，不引入新的辅助类。

**Tech Stack:** PHP 8.3、Laravel 13、Pest 4/5 + Orchestra Testbench。

**Spec:** 无独立 spec。本计划直接源自对 `AGENTS.md` 约束与代码现状的比对；每条修补都对应一条 `AGENTS.md` 已写明的规则或一处可观测的不一致。

## Global Constraints

- `composer test` 是唯一门禁：PHPStan level 7（0 错误）→ Pint 无问题 → 类型覆盖 100% → Pest 全绿。不得在未拿到真实输出前声称通过。
- 不得引入 `{!! !!}`。`grep -rn '{!!' resources/views` 必须无输出。
- `src/` 下新增文件必须声明 `strict_types=1`（`tests/ArchTest.php` 强制）。
- `lang/en/admin.php` 与 `lang/zh_CN/admin.php` 的键集必须完全一致。
- 不使用 `dd()`、`ddd()`、`env()`、`exit()`。

## Review Focus

1. **翻译后的 403 消息在中文环境下不再是英文。** 切换 `app.locale` 到 `zh_CN` 后删除内置管理员角色，提示应变成中文。→ Task 2 的 `tests/Feature/Resource/RolesTest.php` 新增 locale 断言。
2. **`messages.placeholder` 在 zh_CN 下回退显示英文，而非泄漏原始键名。** 宿主应用设为中文时，用户看到的是 `en` 的译文，不是键名字面量。→ Task 2 Step 1。
   > **计划假设被证伪（2026-10-04 执行时）：** 本条原写作「返回字面量 `blatui-admin::messages.placeholder`」，Step 1 的初版测试也确实通过。原因是 Laravel 翻译器在当前 locale 缺文件时回退 `fallback_locale`（默认 `en`），因此不会泄漏键名。测试已改为直接断言中文译文 `后台占位翻译。`，随即正确失败并由新增的 `lang/zh_CN/messages.php` 修复。缺陷本身仍然存在，只是成因与表现与原描述不同。
3. **改用 `__()` 后既有断言会悄悄变成同义反复。** `tests/Feature/ResourceControllerDestroyTest.php:128` 断言字面量 `'Deleted successfully'`；因为该键在 `en` 下恰好解析为同一字符串，测试仍会通过，但从此与 locale 耦合。→ Task 2 Step 9。

---

### Task 1: 工作区遗留物清理与忽略规则

**Files:**
- Delete: `update.py`
- Modify: `.gitignore`
- Modify (restore): `.agents/settings.json`

**Interfaces:**
- Consumes: 无
- Produces: `.gitignore` 新增 `/.agents/.cache/` 与 `/*.png` 两条规则；仓库工作区仅剩 `.agents/skills/run-blatui-admin/` 待Task 3 处理。

- [x] **Step 1: 确认 `update.py` 的作用已被提交历史覆盖**

Run: `git log --oneline -1 -- AGENTS.md README.md lang/en/admin.php lang/zh_CN/admin.php`
Expected: `2af4c0c docs: record resource pages architecture and translations`。脚本内三处 `str.replace` 目标均已出现在该提交中，无残留作用。

- [x] **Step 2: 删除临时脚本**

Run: `rm update.py`

- [x] **Step 3: 追加忽略规则**

在 `.gitignore` 末尾追加：

```gitignore
# Agent tooling run cache (not the tracked skills)
/.agents/.cache/

# Local UI smoke-test screenshots
/*.png
```

**不得**写 `.agents`：`.agents/skills/` 下有 192 个已跟踪文件需要保留。

- [x] **Step 4: 验证忽略规则生效且未误伤已跟踪文件**

Run: `git status --porcelain` — `update.py`、`.agents/.cache/`、`dashboard.png`、`login.png` 均不再出现。
Run: `git ls-files .agents | wc -l` — Expected: `192`。

- [x] **Step 5: 恢复 `.agents/settings.json`**

该文件多出`"defaultMode": "bypassPermissions"`，属外部运行时改动且放宽权限边界。
Run: `git checkout -- .agents/settings.json`

- [x] **Step 6: 提交**

```bash
git add .gitignore
git commit -m "chore: ignore agent run cache and local screenshots"
```

---

### Task 2: 语言包对称性与硬编码文案

**Files:**
- Create: `lang/zh_CN/messages.php`
- Modify: `src/Http/Controllers/ResourceController.php:152`、`:210`
- Modify: `src/Http/Controllers/Resources/AdministratorsController.php:144`
- Modify: `src/Http/Controllers/Resources/RolesController.php:91`
- Modify: `resources/views/auth/login.blade.php:93`
- Modify: `tests/Feature/ResourceControllerDestroyTest.php:128`
- Modify: `tests/Feature/ExampleTest.php`
- Modify: `tests/Feature/Resource/RolesTest.php`

**Interfaces:**
- Consumes: 已存在于两个语言包的键 `deleted`、`cannot_delete_self`、`cannot_delete_admin_role`、`remember_me`（各语言包 19 键，差集为空）。
- Produces: `ResourceController::destroy()` 与 `batchDestroy()` 的 JSON `message` 字段、两处 `authorizeDestroy()` 的 403 消息、登录页 Remember me 文案，四者全部由 `__('blatui-admin::admin.*')` 解析。

- [x] **Step 1: 写失败测试——zh_CN 下messages 键有中文译文**

在 `tests/Feature/ExampleTest.php` 新增：

```php
it('resolves the placeholder translation under zh_CN', function () {
    app()->setLocale('zh_CN');

    expect(trans('blatui-admin::messages.placeholder'))->toBe('后台占位翻译。');
});
```

断言中文译文本身，而非「不等于键名」——后者会被 Laravel 的 `fallback_locale` 回退机制掩盖而假通过（见Review Focus 第 2 条）。

- [x] **Step 2: 写失败测试——403 消息随 locale 变化**

在 `tests/Feature/Resource/RolesTest.php` 新增：

```php
test('the administrator role delete refusal is localized', function () {
    $role = Role::query()->where('slug', 'administrator')->firstOrFail();

    app()->setLocale('zh_CN');

    $this->deleteJson("/admin/auth/roles/{$role->id}")
        ->assertForbidden()
        ->assertSee('内置管理员角色不能删除。');
});
```

若 `assertSee` 对 JSON 响应不生效，改用 `->assertJsonPath('message', '内置管理员角色不能删除。')`——`abort(403, $message)` 经 Laravel 异常处理器渲染为 `{"message": "..."}`。

- [x] **Step 3: 运行测试确认失败**

Run: `vendor/bin/pest tests/Feature/ExampleTest.php tests/Feature/Resource/RolesTest.php`
Expected: 两个新测试 FAIL。实际输出（2026-10-04）：
- `RolesTest`：`Expected '内置管理员角色不能删除。' / Actual 'Cannot delete the administrator role.'`
- `ExampleTest`：`Expected '后台占位翻译。' / Actual 'Admin placeholder translation.'`

- [x] **Step 4: 创建 `lang/zh_CN/messages.php`**

```php
<?php

declare(strict_types=1);

return [
    'placeholder' => '后台占位翻译。',
];
```

Pint 的 `single_blank_line_at_eof` 要求文件末尾恰好一个空行，首次门禁运行时报此 fixer 失败，已由 `vendor/bin/pint lang/zh_CN/messages.php` 修正。`lang/` 下的语言文件在 `pint.json` 的扫描范围内，新文件同样受格式约束。

- [x] **Step 5: 替换 `ResourceController` 两处消息**（`:152`、`:210`）

```php
'message' => (string) __('blatui-admin::admin.deleted'),
```

加 `(string)` 强转的原因：`__()` 返回 `string|array|null`（`vendor/laravel/framework/src/Illuminate/Foundation/helpers.php:1023`），PHPStan level 7 不会自动收窄。

- [x] **Step 6: 替换 `AdministratorsController` 自删消息**（`:144`）

```php
'message' => (string) __('blatui-admin::admin.cannot_delete_self'),
```

- [x] **Step 7: 替换 `RolesController` 403 消息**（`:91`）

```php
abort(403, (string) __('blatui-admin::admin.cannot_delete_admin_role'));
```

- [x] **Step 8: 替换登录页硬编码文案**（`resources/views/auth/login.blade.php:93`）

```blade
<span class="text-xs text-gray-600 dark:text-gray-400">{{ __('blatui-admin::admin.remember_me') }}</span>
```

该键在两个语言包中均已存在。

- [x] **Step 9: 修正会变成同义反复的既有断言**（`tests/Feature/ResourceControllerDestroyTest.php:128`）

```php
->and($response->getData(true))->toMatchArray(['status' => true, 'message' => __('blatui-admin::admin.deleted')])
```

**不要**改 `tests/Unit/GridRowTest.php:239`——那行断言的是 `src/Grid/Actions/Delete.php` heredoc 子系统输出的 JS toast，属另一项架构债。

- [x] **Step 10: 验证测试通过**

Run: `vendor/bin/pest tests/Feature/ExampleTest.php tests/Feature/Resource/RolesTest.php tests/Feature/Resource/AdministratorsTest.php tests/Feature/ResourceControllerDestroyTest.php`
Expected: 全部 PASS，原有 212 个测试无回归。

- [x] **Step 11: 校验语言包键集对称**

Run: `php -r '$e=require "lang/en/admin.php";$c=require "lang/zh_CN/admin.php";$d=array_merge(array_diff(array_keys($e),array_keys($c)),array_diff(array_keys($c),array_keys($e)));echo $d?"MISMATCH: ".json_encode($d):"OK ".count($e)." keys";'`
Expected: `OK 19 keys`。

- [x] **Step 12: 跑完整门禁**

Run: `composer test`
Expected: PHPStan 0 errors、Pint passed、类型覆盖 100.0%、Pest 214 passed。

- [x] **Step 13: 提交**

```bash
git add lang/zh_CN/messages.php src/Http/Controllers/ResourceController.php \
  src/Http/Controllers/Resources/AdministratorsController.php \
  src/Http/Controllers/Resources/RolesController.php \
  resources/views/auth/login.blade.php \
  tests/Feature/ExampleTest.php tests/Feature/Resource/RolesTest.php \
  tests/Feature/ResourceControllerDestroyTest.php
git commit -m "fix(i18n): localize resource page messages and complete zh_CN messages"
```

---

### Task 3: 文档与版本号一致性

**Files:**
- Modify: `docs/superpowers/specs/2026-10-04-rbac-resource-pages-design.md:1`
- Modify: `docs/superpowers/plans/2026-10-04-rbac-resource-pages.md:3`
- Modify: `resources/views/partials/sidebar.blade.php:27`
- Add: `.agents/skills/run-blatui-admin/SKILL.md`、`.agents/skills/run-blatui-admin/driver.sh`

**Interfaces:**
- Consumes: Task 1 清理后的干净工作区
- Produces: 全部 6 份 spec 与 6 份计划的状态头合规；侧边栏版本号与 CHANGELOG 一致；UI 烟测 skill 纳入版本控制。

- [x] **Step 1: 修正 spec 状态头**

`docs/superpowers/specs/2026-10-04-rbac-resource-pages-design.md:1` 当前为 `> **Status: APPROVED (2026-10-04)`。`APPROVED` 不在 `AGENTS.md` 规定的三个值（`COMPLETE` / `IN PROGRESS` / `SUPERSEDED`）之内，而该 spec 全部内容已实现并通过门禁。改为 `> **Status: COMPLETE (verified 2026-10-04)**`。

- [x] **Step 2: 修正 rbac 计划状态头的提交数表述**

`docs/superpowers/plans/2026-10-04-rbac-resource-pages.md:3` 写"as thirteen commits"，实际为 14 个（含 `833d41f`），且分支已快进合入 `main`。改为 fourteen 并补记合并事实。

- [x] **Step 3: 修正侧边栏版本号**

`resources/views/partials/sidebar.blade.php:27` 硬编码 `v1.0.0`，而 `CHANGELOG.md` 显示当前为 `v0.1.0`。改为 `v0.1.0`。

- [x] **Step 4: 提交 `run-blatui-admin` skill**

`.agents/skills/` 下其余 21 个 skill 均已跟踪，纳入版本控制与既有惯例一致。

先修一处路径错误——`SKILL.md` 中写 `.claude/skills/run-blatui-admin/driver.sh`，实际路径是 `.agents/skills/run-blatui-admin/driver.sh`，按原文照抄会找不到脚本。

```bash
git add .agents/skills/run-blatui-admin/
git commit -m "chore(skills): add run-blatui-admin UI smoke-test driver"
```

- [x] **Step 5: 提交文档修补**

```bash
git add docs/superpowers/specs/2026-10-04-rbac-resource-pages-design.md \
  docs/superpowers/plans/2026-10-04-rbac-resource-pages.md \
  resources/views/partials/sidebar.blade.php
git commit -m "docs: correct spec status header and version display"
```

- [x] **Step 6: 跑完整门禁并更新本计划状态头**

Run: `composer test`
Expected: PHPStan 0 errors、Pint passed、类型覆盖 100.0%、Pest 214 passed。

将本文件顶部状态头更新为 `COMPLETE`，附实测门禁输出。

---

## 明确排除在本计划之外的两项

按 writing-plans 的 Scope Check，这两项各自是独立子系统：

**其一，`src/` 中残留的 heredoc。** `AGENTS.md` 规定 "Zero HTML string concatenation or heredocs in `src/`"，但 `grep -rn '<<<' src/` 返回 7 处：`src/Grid/Tools.php` 五处、`src/Grid/Tools/BatchDelete.php:119`、`src/Grid/Actions/Delete.php:98`。上轮渲染架构重构只清理了 `src/Grid/Filter/`。按 `AGENTS.md` 裁决顺序，这是代码违反约束，需独立重构（每个类提取 Blade 视图 + 变量数组），会触及 `tests/Unit/GridRowTest.php` 等既有断言。

**其二，侧边栏悬空的 `auth/logs`。** `database/seeders/AdminTablesSeeder.php:161` 种子化了指向 `auth/logs` 的菜单项与 `/auth/logs*` 权限，`OperationLog` 模型存在，但既无路由也无控制器，点击必然 404。`docs/superpowers/specs/2026-10-04-rbac-resource-pages-design.md:251` 已明确记载本页不在当时构建范围内，故属显式取舍而非遗漏——但对用户仍是破损链接。补齐等于新增第五个资源页，需独立 spec。