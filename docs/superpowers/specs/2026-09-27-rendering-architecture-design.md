# BlatUI Admin - 渲染架构与安全设计规范 (Rendering Architecture & Security Design)

- **主题**: 彻底重构底层渲染引擎，消除隐式 XSS 风险，全面对齐现代 Laravel 13 最佳实践
- **创建日期**: 2026-09-27
- **状态**: Approved (Drafting)
- **目标**: 保留原有的高封装度 DSL（以编程方式定义视图），但将其内部的“PHP 字符串拼接”替换为类型安全的组件化渲染模型。

---

## 1. 核心架构问题与背景

Dcat Admin 的历史遗留架构在视图渲染中存在严重的安全隐患与设计缺陷：
- **职责越界 (God Classes)**：`Content`、`Row`、`Column` 等基类在内部硬编码拼接 HTML 字符串（如 `$html .= '<div...>'`）。
- **隐式安全假设**：Blade 视图（如 `table.blade.php`）中大量滥用 `{!! !!}`。这建立在“PHP 层必须确保数据绝对安全”的脆弱假设上，极易产生 XSS 漏洞。
- **缺乏类型约束**：向视图传递数据往往采用松散的 `array`，丢失了现代框架的类型安全防线。

## 2. 架构重构蓝图 (Architectural Modernization)

针对上述缺陷，本项目确立以下三个维度的现代架构重构标准：

### 2.1 视图模型分离 (ViewModel Separation)
- **定义**：剥离所有 PHP 类中的前端渲染职责。
- **规范**：PHP 基类彻底退化为**纯粹的数据传输对象 (DTO) 或视图模型 (ViewModel)**。它们只负责收集业务逻辑和状态，不再生成任何原始 HTML。所有的 DOM 结构、Tailwind CSS 类名、Alpine.js 指令，必须严格收敛存放于 `resources/views/` 下的 Blade 文件中。

### 2.2 全面接入 Laravel 原生契约 (Implementing Native Laravel Contracts)
- **定义**：抛弃私有的、容易绕过防御的渲染方法，改用 Laravel 官方标准。
- **规范**：所有自定义的 UI 块（如 `Grid\Filter`, `Grid\Tools`, `Column\Displayer`）必须实现 `Illuminate\Contracts\Support\Htmlable`（或 `Renderable`）接口。
- **视图侧落地**：在 Blade 视图中，抛弃 `{!! $component->render() !!}` 的危险写法，统一改为原生的 `{{ $component }}`。由 Laravel 底层引擎在解析时自动识别其 `Htmlable` 契约，实现天然安全的渲染闭环。

### 2.3 基于 IoC 容器的防御性编程 (Defensive Programming via IoC)
- **定义**：在数据流入 Blade 引擎之前，建立强制的“网关拦截”。
- **规范**：利用 `AdminServiceProvider` 向服务容器注册 `ViewComposer`（例如 `GridViewComposer`）。作为最终的安全网，Composer 负责在控制器与视图交界的最后关头，对注入到顶级布局的变量（如 `$title`, `$description`）进行强制类型校验与深度净化。

## 3. 落地实施边界 (Implementation Scope)
1. **替换遗留代码**：扫描并重写 `Content.php` 等布局文件中的 `renderFallback()` 及类似硬编码逻辑。
2. **升级显示器基类**：确保所有内置的 Displayer 严格遵守 `HtmlString` 返回类型。
3. **改造 Blade 模版**：清理所有视图文件中非必要的 `{!! !!}`，重构为由原生组件或 `{{ }}` 驱动的安全调用。
4. **单元与安全测试**：建立专属的 `tests/Security/` 套件，针对边界输入的转义行为进行严格断言。
