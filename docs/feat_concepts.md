# 特性概念草案：通用数据契约、Fluent DX 与生成式 UI (GenUI) 架构探讨

> **Status:** DRAFT (概念讨论与待定备忘，未定论)  
> **Topic:** 探索替代传统 Class-per-Component 重型封装的通用数据处理与现代化架构。

---

## 1. 痛点背景 (Background & Problems)

在传统的后台框架（如 Dcat Admin 和 Filament）中，均采用典型的 **Class-per-Component（一组件一类）** 架构模式：
1. **类爆炸 (Class Explosion)**：后台包含数十上百个 Field 和 Filter 类（Dcat 拥有 60+ Field 类，Filament 拥有庞大的组件类层次结构），每个类均需定义相似的链式 setter（`placeholder`, `rules`, `help`, `disabled` 等），充斥数万行样板代码。
2. **前后端双重维护成本 (Dual-Maintenance)**：前端（BlatUI Blade 组件）新增或调整一个 HTML 属性、Tailwind class 或 Alpine 事件，后端 PHP 类就必须同步适配，前后端迭代相互阻塞。
3. **扼杀前端灵活性**：过度的 PHP 面向对象包装，把 Blade + Tailwind CSS v4 + Alpine.js 原生声明式的灵活性限制在后端的死板抽象中。

---

## 2. 核心架构设想 (Architecture Concepts)

### 2.1 从“硬编码组件类”转向“通用数据封装与 GenUI 架构”
借鉴 **Generative User Interface (GenUI) / Server-Driven UI (SDUI)** 的核心原则：
> **后端定义“数据流、意图与语义契约”，前端负责“动态装配与渲染呈现”。**

- 后端不再为每个 BlatUI 组件编写具体的 PHP 包装类，而是建立高内聚、轻量级的通用数据处理契约（Generic Schema & Data Processor）。
- 前端通过 `<x-dynamic-component>` 与属性穿透直接渲染原生 BlatUI 组件。
- 该元数据契约支持序列化为 JSON，天然为未来由 AI / 上下文动态生成和调整界面（GenUI）预留架构接口。

---

## 3. 现代 PHP 8+ 底座技术选型 (Modern PHP Foundations)

在底层核心机制上，优先拥抱现代 PHP 特性：

1. **PHP 8 Attributes 原生注解**：
   - 声明式元数据驱动，将字段属性、校验规则、UI 映射、关联关系直接内聚在数据模型或 DTO 上（如 `#[Field]`, `#[Rule]`, `#[Cast]`, `#[Relation]`）。
   - 兼具易读性与反射能力，可自描述为 UI Schema。
2. **强类型 Schema 与 DTO**：
   - 摆脱松散关联数组，依托原生强类型保证数据边界，严格契合 PHPStan Level 8+ 与 100% Type Coverage。
3. **独立的数据后处理管线 (Data Post-Processing Pipeline)**：
   - 将“数据存取逻辑”从 UI 表现中彻底剥离，划分为通用的处理管道：
     - 标量清洗与转换（Casting & Sanitization）
     - 数据规则校验（Validation）
     - 数据后处理钩子（Mutators & Hooks）
     - 关联关系持久化（Relation Sync / BelongsToMany Pivot 同步）

---

## 4. 开发者体验 (DX) 与 Laravel Fluent 结合

### 4.1 借鉴 Laravel `Blueprint\ColumnDefinition` 的 Fluent 机制
- Laravel 数据库迁移中的 `ColumnDefinition` 直接继承 `Illuminate\Support\Fluent`，通过 `__call` 动态捕获任意无参或有参调用（如 `->nullable()->default('admin')`）。
- **零成本属性穿透**：通用字段封装类包装或继承 `Fluent`，使得 BlatUI 组件所支持的任意前端 prop（`placeholder`, `clearable`, `prefix`, `variant`, `disabled` 等）都能直接通过链式调用捕获，后端无需编写任何冗余 setter。
- **生态 Trait 注入**：
  - 引入 `Conditionable`：支持 `->when($condition, fn($f) => ...)` 条件式 UI 配置。
  - 引入 `Macroable`：允许宿主项目自由挂载通用宏方法。

---

## 5. 后续待定决策点 (Pending Decisions & Research)

- [ ] **DSL 表面语法风格调研**：是在通用内核之上保留常用高频语法糖（如 `$form->text()`），还是完全统一为抽象语法（如 `$form->field('name')->as('input')`）？待后续实际应用场景深入后再做定论。
- [ ] **Attribute 注解解析与缓存机制**：注解反射性能优化与动态重载机制。
- [ ] **Blade 属性绑定的完整契约**：Fluent 属性数组如何与 Alpine `x-bind`、Blade `$attributes` 最优雅地桥接。
