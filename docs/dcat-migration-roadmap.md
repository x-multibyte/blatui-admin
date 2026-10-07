# Dcat Admin 特性迁移与现代化演进路线 (Dcat Migration Roadmap)

> **Status:** IN PROGRESS (Baseline verified 2026-10-05 via GitNexus)  
> **Target:** 指导 `x-multibyte/blatui-admin` 承接 `dcat-admin` 核心能力的演进方向与阶段规划。

---

## 1. 核心迁移策略 (Core Strategy)

本项目定位为现代化的 Laravel 独立管理后台扩展包，采用 **“抄两头，换中间”** 的重构原则：

1. **继承 DSL 开发者心智**：完整复用并对齐 Dcat Admin 经过成熟验证的链式调用语法（`Grid` / `Form` / `Show` / `Content`）与数据模型仓库层，让熟悉 Dcat 的开发者零门槛上手。
2. **重塑底层渲染与安全边界**：
   - 彻底废弃历史遗留的技术栈（jQuery、Pjax、Bootstrap 4、toastr、sweetalert2）。
   - 全面拥抱现代 **BLAT 前端技术栈**（Blade + Laravel 11/12 + Alpine.js + Tailwind CSS v4）。
   - 推行 **ViewModel 架构**：PHP 类仅作为纯粹的数据传输对象与状态容器，必须实现 `\Illuminate\Contracts\Support\Htmlable`，严禁在 PHP 中拼接 HTML 字符串或内嵌 JavaScript；视图呈现完全由 Blade 模板原生输出与 `{{ }}` 编译期安全转义控制，从架构根源阻断 XSS 风险。
3. **拒绝臃肿与盲目全量兼容**：只迁移高频、通用、现代的后台核心能力，坚决淘汰不适合现代包生态的过时设计。

---

## 2. 宏观代码与模块迁移基线 (Baseline Comparison)

依据 GitNexus 知识图谱对两工程的代码符号与依赖分析，当前迁移基线如下：

| 模块类别 | Dcat Admin (源工程) | BlatUI Admin (现状) | 迁移演进说明 |
| :--- | :--- | :--- | :--- |
| **代码体量** | 1,495 文件 / 25,074 符号 | 216 文件 / 1,856 符号 | 剥离历史前端依赖产物与冗余封装，代码库轻量化 85% |
| **数据表与模型** | 7 张核心数据表 | 7 张核心表 (100% 对齐) | `Administrator`, `Role`, `Permission`, `Menu`, `OperationLog` 表结构与关联模型完全对齐 |
| **RBAC 管理页面** | 5 大系统管理页 | 4 大页面已完备 (单测 100%) | 用户、角色、权限、菜单 CRUD 已完备；操作日志展示页规划中 |
| **权限中间件** | 7 个复杂中间件栈 | 2 个轻量现代中间件 | 保留核心认证 `admin.auth` 与细粒度路由/路径权限拦截 `admin.permission` |
| **表单字段 (Field)** | 60 种字段类 (大量旧插件) | 10 种高频字段 | 已落地常见输入、下拉、开关及多对多 Pivot 关联 (`Multiselect`/`Relation`) |
| **表格筛选 (Filter)**| 37 种筛选条件 | 11 种核心对比筛选 | 已落地等于、模糊匹配、范围比较、In 等基础高频查询 |
| **列渲染器 (Displayer)**| 30 种展示方式 | 8 种基础展示器 | 包含徽章 Badge、复制 Copyable、日期 Datetime、链接 Link、图片 Image 等 |
| **Artisan 命令** | 24 个繁复控制台命令 | 5 个现代化交互命令 | 基于 `laravel/prompts` 重构，支持对称发布 (`admin:publish`) 与卸载 (`admin:uninstall`) |
| **UI 部件 (Widgets)** | 31 个 PHP Widget 类 | 替换为原生 Blade 组件 | 由 `<x-ui.*>` (Card, Dialog, Table, Sonner 等) 现代组件全面替代后端拼装 |

---

## 3. 后续演进阶段与优先级 (Roadmap & Phases)

### 阶段一：补齐核心开发利器与悬空入口 (高优先级)
- [ ] **代码生成脚手架 (`admin:make`)**：
  - 借鉴 Dcat 的 `Scaffold` 思维，通过命令行交互读取数据库表元信息，一键生成 Controller、Model 与基础 `grid()` / `form()` 模板代码。
- [x] **操作日志管理页 (`auth/logs`)**：
  - 补全目前侧边栏唯一悬空的系统功能，实现 `OperationLogController`，记录并查看后台操作日志（IP、Method、Path、Input）。

### 阶段二：常用表单与表格交互扩充 (中优先级)
- [ ] **高频表单字段补齐**：
  - 现代化富文本 / Markdown 编辑器（结合 Alpine / TipTap 或轻量 Markdown 方案）。
  - 文件 / 图片上传增强（`<x-ui.file-upload>` 配合异步上传驱动）。
  - 常用辅助字段（Radio 单选、Checkbox 多选、Tags 标签输入）。
- [ ] **表格交互增强**：
  - 行内快速切换开关（`switch()` 列展示与即时 API 交互）。
  - 筛选器快捷作用域标签（`scope()` Tab 切换）。

### 阶段三：详情页与生态扩展 (低优先级)
- [ ] **Show 详情页完善**：
  - 完善只读详情展示器与对应的 Blade 模板视图。
- [ ] **数据导出扩展**：
  - 基于流式响应提供轻量 CSV / Excel 导出能力。

---

## 4. 明确排除与废弃的设计 (Rejected / Out of Scope)

依据项目架构治理要求，以下 Dcat 原生特性明确不在迁移范围内，避免重复制造技术债：

1. **废弃旧版扩展包系统 (`Extend/`)**：
   - 不再自建“应用市场/本地 Extension 插件管理器”，完全遵循标准 Composer / Laravel Package 生态进行功能扩展。
2. **废弃 PHP 内部 Widget 封装 (`Widgets/`)**：
   - 不再提供类似 `Widgets\Box`, `Widgets\Card`, `Widgets\Modal` 等 PHP 拼接类，全面交由 Blade 组件与 Tailwind CSS 声明式书写。
3. **排除特定运行时层 (`Octane/FlushAdminState`)**：
   - 保持框架轻量和环境中立，不强绑定或堆砌特定运行时的状态重置黑魔法。
4. **排除树形嵌套拖拽排序组件**：
   - 菜单及层级管理采用打平缩进的 Select / 单选模式，避免引入重型拖拽 JS 库。
