# BlatUI Admin 设计规范 (Technical Design Specification)

- **项目名称**: `x-multibyte/blatui-admin`
- **命名空间**: `BlatUI\Admin`
- **目标环境**: PHP ^8.3, Laravel ^12.0 || ^13.0
- **创建日期**: 2026-09-27
- **设计范式**: 现代 Dcat 架构 (PHP Fluent Builder DSL) + BLAT 前端栈 (Blade + Tailwind CSS v4 + Alpine.js)

---

## 1. 核心设计理念与定位

### 1.1 核心思想：“拿来就用主义（抄两头，换中间）”
避免从零重新造轮子，充分复用开源生态中最成熟的成果，以最低成本、最高稳定性构建现代化后台扩展包：
1. **复用 Dcat Admin 的 PHP 内核引擎**：直接借鉴移植 `/root/projects/dcat-admin` 成熟的数据仓库抽象、CRUD 生命周期钩子、查询过滤解析、树形菜单与权限校验逻辑。
2. **复用 BlatUI 的现代前端组件**：直接搬运 `https://blatui.remix-it.com`（基于 Tailwind CSS v4 与 Alpine.js 的现代化 shadcn 风格 Blade 组件库），告别枯燥的手写 CSS。
3. **彻底换掉过时中间层**：彻底剔除 Dcat 历史包袱（jQuery、Pjax、Bootstrap、toastr、sweetalert2 以及在 PHP 中拼接内联 JavaScript 的旧设计），全部重构为 **Alpine.js 声明式指令 + 原生 Fetch API + BlatUI Sonner Toast / AlertDialog**。
4. **简洁命令规范**：命令行一律使用亲切短小的 `admin:xxx` 格式（如 `admin:install`, `admin:make`），杜绝冗长的包名前缀。

---

## 2. 系统架构与目录结构

源码完全对齐 Dcat Admin 经过成熟验证的精细化分层（去除 Octane），规划如下：

```text
src/
├── Actions/              # 行操作、批量操作、表单操作体系 (RowAction, BatchAction, FormAction)
├── Console/              # 命令行工具 (admin:install, admin:make, admin:create-user, admin:publish)
├── Contracts/            # 核心契约接口 (Repository, Renderable, Tree, Grid/Form 规范)
├── Exception/            # 统一异常与处理 Handler
├── Extend/               # 扩展插件管理器与生命周期 (Extension, Manager, Version)
├── Form/                 # 表单构造器 DSL (Field, Layout, NestedForm, Resolver)
├── Grid/                 # 列表构造器 DSL (Column, Filter, Displayers, Tools, Exporters)
├── Http/                 # 核心控制器、中间件与路由
│   ├── Controllers/      # AdminController(基类), AuthController, MenuController, RoleController, PermissionController, LogController
│   └── Middleware/       # Authenticate, Permission, OperationLog
├── Layout/               # 页面容器渲染引擎 (Content, Row, Column, Navbar, Sidebar)
├── Models/               # 核心数据模型 (Administrator, Role, Permission, Menu, OperationLog, Setting)
├── Repositories/         # 数据仓库解耦层 (Repository 接口与 EloquentRepository 实现)
├── Scaffold/             # 智能脚手架引擎 (ControllerCreator, ModelCreator, TableSchema)
├── Show/                 # 详情页构造器 DSL (Panel, Field, Row)
├── Support/              # 辅助工具类 (Helper, Color, BladeCompiler, Setting)
├── Traits/               # 通用复用能力 (HasPermissions, ModelTree, RespondsWithHttpStatus)
├── Tree/                 # 树形结构视图构造器 (ModelTree，用于菜单、分类层级管理)
├── Widgets/              # 独立挂件库 (Card, Modal, Dropdown, Tab, Stat, Metrics)
├── Admin.php             # 核心服务入口与上下文管理门面类
└── AdminServiceProvider.php
```

---

## 3. 基础设施与数据库设计 (Auth & RBAC)

系统使用独立的 `admin` Guard 与专属数据表，与前台应用完全隔离：

### 3.1 核心数据表结构
1. `admin_users`: 管理员主表（id, username, password, name, avatar, remember_token, timestamps）。
2. `admin_roles`: 角色表（id, name, slug, timestamps）。
3. `admin_permissions`: 权限表（id, name, slug, http_method, http_path, order, parent_id, timestamps）。
4. `admin_role_users`: 用户与角色多对多关联表。
5. `admin_role_permissions`: 角色与权限多对多关联表。
6. `admin_menu`: 后台多级动态导航菜单（id, parent_id, order, title, icon, uri, extension, show, timestamps）。
7. `admin_operation_log`: 操作审计日志表（id, user_id, path, method, ip, input, timestamps）。

### 3.2 认证与中间件
- 配置文件：`config/blatui-admin.php`，包含路由前缀（默认 `/admin`）、多语言、主题、上传配置等。
- 服务提供者 `AdminServiceProvider`：自动注册 `auth.guards.admin` 与 `auth.providers.admin_users`，挂载核心中间件别名（`admin.auth`, `admin.permission`, `admin.log`）。

---

## 4. 核心 DSL 与 BlatUI 渲染映射

### 4.1 控制器标准开发体验 (Developer API)
开发者保持与 Dcat Admin 100% 一致的心智模型：

```php
namespace App\Admin\Controllers;

use App\Models\Post;
use BlatUI\Admin\Controllers\AdminController;
use BlatUI\Admin\Form;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Show;

class PostController extends AdminController
{
    protected string $title = '文章管理';

    protected function grid(): Grid
    {
        return Grid::make(new Post(), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('title')->filter();
            $grid->column('is_published')->switch();
            $grid->column('created_at')->datetime();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
                $filter->like('title');
            });
        });
    }

    protected function form(): Form
    {
        return Form::make(new Post(), function (Form $form) {
            $form->display('id');
            $form->text('title')->required();
            $form->textarea('content');
            $form->switch('is_published')->default(true);
        });
    }

    protected function detail($id): Show
    {
        return Show::make($id, new Post(), function (Show $show) {
            $show->field('id');
            $show->field('title');
            $show->field('created_at');
        });
    }
}
```

### 4.2 Dcat 字段到 BlatUI Blade 组件映射表

| Dcat DSL 字段 / 控件 | 底层渲染的 BlatUI Blade 组件 | 交互驱动机制 |
| :--- | :--- | :--- |
| **Grid Table** | `<x-ui.table>`, `<x-ui.table-row>`, `<x-ui.table-cell>` | 原生 Tailwind CSS v4 样式 |
| **Grid 批量操作** | `<x-ui.dropdown-menu>` + `<x-ui.button>` | Alpine.js `selectedRows: []` 状态监听 |
| **Grid 过滤栏** | `<x-ui.card>`, `<x-ui.input>`, `<x-ui.select>` | 标准 HTTP GET 查询拼接提交 |
| **Grid 分页** | `<x-ui.pagination>` | Laravel 原生 LengthAwarePaginator 渲染 |
| **$form->text()** | `<x-ui.input>` | 原生 HTML5 / Alpine 双向绑定 |
| **$form->textarea()** | `<x-ui.textarea>` | 自动自适应行高 |
| **$form->switch()** | `<x-ui.switch>` | Alpine.js `x-model` 切换布尔值 |
| **$form->select()** | `<x-ui.select>` / `<x-ui.combobox>` | Alpine 驱动的下拉弹出层 |
| **$form->datetime()** | `<x-ui.datetime-picker>` | BlatUI 原生日期时间浮层组件 |
| **$form->file() / image()** | `<x-ui.file-upload>` | 拖拽与缩略图上传展示 |
| **行内删除 / 确认框** | `<x-ui.alert-dialog>` | Alpine.js 控制弹窗，确认后触发 `fetch()` 请求 |
| **全局操作反馈** | `<x-ui.sonner>` | 替换 toastr，监听 Laravel Flash 或后端 JSON 响应 |

---

## 5. 命令行工具规范 (Artisan Commands)

统一采用 `admin:xxx` 极简命令格式：

1. **`admin:install`**
   - 运行数据库迁移，建立 7 张核心表。
   - 创建默认超级管理员账号（默认 `admin` / `admin`）。
   - 发布配置文件 `config/blatui-admin.php`。
   - 发布基础 BlatUI 组件与后台路由 `routes/admin.php`。
2. **`admin:make [Model/Table]`**
   - 智能读取指定数据表的字段类型、约束与注释。
   - 自动生成绑定对应模型仓库的 Controller、Model（如需）与 Migration（如需）。
   - 预先生成完整的 `grid()`, `form()`, `detail()` 方法。
3. **`admin:create-user`**
   - 在命令行交互式创建后台管理员。
4. **`admin:publish`**
   - 一键或按标签发布资源（语言包、配置、视图组件）。

---

## 6. 实施路线图 (Implementation Phases)

- **第一阶段（基础设施与模型移植）**：
  - 导入 Dcat 的 `Contracts/`, `Models/`, `Repositories/`, `Support/`, `Traits/`, `Exception/`。
  - 调整命名空间为 `BlatUI\Admin`，去除 Octane 依赖。
  - 实现迁移文件与 `admin:install`、`admin:create-user`。
- **第二阶段（BlatUI 前端底座与后台 Layout）**：
  - 搬运 BlatUI 核心组件（Button, Input, Table, Card, Dialog, Sonner, Dropdown, Sidebar）。
  - 构建后台全局主布局（侧边栏菜单树、顶部导航、暗黑模式切换、Sonner 提示桥接）。
  - 实现登录、登出与个人资料页。
- **第三阶段（Grid / Form / Show DSL 引擎对接）**：
  - 改造 Grid 渲染层，连接 BlatUI `<x-ui.table>` 与操作列。
  - 改造 Form 字段系统，依次实现高频输入组件的 BlatUI Blade 模板。
  - 实现基于 Fetch API + Sonner 的异步行操作与删除确认。
- **第四阶段（Scaffold 代码生成器与系统内置页面）**：
  - 移植代码生成器，支持 `admin:make` 自动化一键生成。
  - 实现菜单管理页面（基于树形组件）、角色管理、权限管理与操作日志。
- **第五阶段（自动化测试与质量加固）**：
  - 编写 Pest 测试（单元测试、特性测试、命令测试）。
  - 运行 `composer test`（PHPStan 静态分析、Pint 代码格式化、类型覆盖率检查）。
