# BlatUI Admin 设计规范 (Technical Design Specification)

- **项目名称**: `x-multibyte/blatui-admin`
- **命名空间**: `BlatUI\Admin`
- **目标环境**: PHP ^8.3, Laravel ^12.0 || ^13.0
- **创建日期**: 2026-09-27
- **状态**: COMPLETE（2026-09-28 验证）。本文档描述的整体设计已全部落地，其分解计划 `docs/superpowers/plans/2026-09-27-blatui-admin-foundation.md`、`2026-09-27-blatui-admin-grid-engine.md`、`2026-09-27-blatui-admin-layout-and-views.md` 均标记为 COMPLETE 且经产物核对验证。保留作为参考，无待办工作。
- **设计范式**: 现代 Dcat 架构 (PHP Fluent Builder DSL) + BLAT 前端栈 (Blade + Tailwind CSS v4 + Alpine.js)

---

## 1. 核心设计理念与战略：“拿来就用主义（抄两头，换中间）”

避免从零重新造轮子，充分复用开源生态中最成熟的成果，以最低成本、最高稳定性构建现代化后台扩展包：
1. **复用 Dcat Admin 的 PHP 内核引擎**：直接借鉴移植 `/root/projects/dcat-admin` 成熟的数据仓库抽象、CRUD 生命周期钩子、查询过滤解析、树形菜单与权限校验逻辑。
2. **复用 BlatUI 的现代前端组件**：直接搬运 `https://blatui.remix-it.com`（基于 Tailwind CSS v4 与 Alpine.js 的现代化 shadcn 风格 Blade 组件库），告别枯燥的手写 CSS。
3. **彻底换掉过时中间层**：彻底剔除 Dcat 历史包袱（jQuery、Pjax、Bootstrap、toastr、sweetalert2 以及在 PHP 中拼接内联 JavaScript 的旧设计），全部重构为 **Alpine.js 声明式指令 + 原生 Fetch API + BlatUI Sonner Toast / AlertDialog**。
4. **简洁命令规范**：命令行一律使用亲切短小的 `admin:xxx` 格式（如 `admin:install`, `admin:make`），杜绝冗长的包名前缀。

---

## 2. 系统架构与精细化目录组织

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

## 3. 核心基石：资源发布、配置、迁移、数据填充与路由 (The Pillars)

扩展包的底层生命线由 **Config、Migrations、Seeders、Routes 与 Resource Publishers** 构成，严格遵循 Laravel 规范与 `package-scaffold` 规则。

### 3.1 配置文件设计 (`config/blatui-admin.php`)

提供完整严谨的配置项，支持在宿主应用中灵活重写：

```php
return [
    'name' => 'BlatUI Admin',
    'title' => 'BlatUI 管理后台',
    'logo' => '<b>BlatUI</b> Admin',

    // 路由配置
    'route' => [
        'prefix' => env('ADMIN_ROUTE_PREFIX', 'admin'),
        'domain' => env('ADMIN_ROUTE_DOMAIN'),
        'middleware' => ['web', 'admin'],
        'namespace' => 'App\\Admin\\Controllers',
    ],

    // 认证与 Guard
    'auth' => [
        'guard' => 'admin',
        'guards' => [
            'admin' => [
                'driver' => 'session',
                'provider' => 'admin',
            ],
        ],
        'providers' => [
            'admin' => [
                'driver' => 'eloquent',
                'model' => BlatUI\Admin\Models\Administrator::class,
            ],
        ],
    ],

    // 核心数据表名定义（支持重命名）
    'database' => [
        'connection' => '',
        'users_table' => 'admin_users',
        'users_model' => BlatUI\Admin\Models\Administrator::class,
        'roles_table' => 'admin_roles',
        'roles_model' => BlatUI\Admin\Models\Role::class,
        'permissions_table' => 'admin_permissions',
        'permissions_model' => BlatUI\Admin\Models\Permission::class,
        'menu_table' => 'admin_menu',
        'menu_model' => BlatUI\Admin\Models\Menu::class,
        'operation_log_table' => 'admin_operation_log',
        'operation_log_model' => BlatUI\Admin\Models\OperationLog::class,
        'role_users_table' => 'admin_role_users',
        'role_permissions_table' => 'admin_role_permissions',
        'role_menu_table' => 'admin_role_menu',
    ],

    // 文件上传存储
    'upload' => [
        'disk' => 'public',
        'directory' => [
            'image' => 'admin/images',
            'file' => 'admin/files',
        ],
    ],

    // 界面与布局风格
    'layout' => [
        'color' => 'default',
        'dark_mode_switch' => true,
        'sidebar_collapsed' => false,
    ],
];
```

### 3.2 数据库迁移设计 (`database/migrations/create_admin_tables.php`)

安装时生成的 7 张核心数据表（结构严格对齐 Dcat 成熟方案）：
1. `admin_users`：管理员主表（id, username, password, name, avatar, remember_token, timestamps）。
2. `admin_roles`：角色表（id, name, slug, timestamps）。
3. `admin_permissions`：权限表（id, name, slug, http_method, http_path, order, parent_id, timestamps）。
4. `admin_menu`：后台多级动态导航菜单（id, parent_id, order, title, icon, uri, extension, show, timestamps）。
5. `admin_role_users`：管理员与角色多对多关联表。
6. `admin_role_permissions`：角色与权限多对多关联表。
7. `admin_role_menu`：角色与菜单多对多授权表。
8. `admin_operation_log`：管理员操作审计日志表（id, user_id, path, method, ip, input, timestamps）。

### 3.3 数据库初始数据填充器 (`database/seeders/AdminTablesSeeder.php`)

提供可重复运行、保证系统安装即用的数据填充：
1. **超级管理员账号**：
   - 用户名：`admin`
   - 默认密码：`admin`
   - 姓名：`Super Administrator`
2. **默认角色**：
   - 名称：`Administrator`，标识：`administrator`，绑定全部权限。
3. **默认基础权限**：
   - `*` (All permissions)
   - `Dashboard` (控制台访问权限)
   - `Auth management` (管理员、角色、权限管理)
4. **默认菜单树**：
   - 🏠 **控制台 (Dashboard)**：`uri: /`
   - ⚙️ **系统管理 (Admin)**：
     - 👤 管理员 (Users)：`uri: auth/users`
     - 🛡️ 角色管理 (Roles)：`uri: auth/roles`
     - 🔑 权限管理 (Permissions)：`uri: auth/permissions`
     - 📑 菜单管理 (Menu)：`uri: auth/menu`
     - 📋 操作日志 (Operation Log)：`uri: auth/logs`

### 3.4 路由系统 (`routes/blatui-admin.php` & `routes/admin.php`)

- **内核加载机制**：
  在 `AdminServiceProvider::boot()` 中自动基于 `config('blatui-admin.route')` 的前缀、域名和中间件包裹核心路由。
- **宿主路由扩展**：
  发布到宿主应用的 `routes/admin.php`，供开发者自由声明自定义业务路由：
  ```php
  use Illuminate\Support\Facades\Route;

  Route::get('/', [DashboardController::class, 'index'])->name('admin.home');
  Route::resource('posts', PostController::class);
  ```

### 3.5 资源发布者规范 (Publish Tags & Service Provider Wiring)

严格按照 `package-scaffold` 规范，在 `AdminServiceProvider` 中注册清晰的发布标签：

| 发布 Tag | 目标路径 | 描述 |
| :--- | :--- | :--- |
| **`blatui-admin`** | *(全量发布)* | 一键发布所有资源（配置、迁移、填充器、路由、视图、语言包） |
| **`blatui-admin-config`** | `config_path('blatui-admin.php')` | 发布后台配置文件 |
| **`blatui-admin-migrations`** | `database_path('migrations')` | 发布 7 张核心表迁移文件 |
| **`blatui-admin-seeders`** | `database_path('seeders')` | 发布初始数据填充器文件 |
| **`blatui-admin-routes`** | `base_path('routes/admin.php')` | 发布后台业务路由定义文件 |
| **`blatui-admin-views`** | `resource_path('views/vendor/blatui-admin')` | 发布后台所有 Blade 视图与 BlatUI 基础组件 |
| **`blatui-admin-lang`** | `lang_path('vendor/blatui-admin')` | 发布 zh_CN / en 语言包 |
| **`blatui-admin-assets`** | `public_path('vendor/blatui-admin')` | 发布静态前端资源（CSS / Alpine 脚本桥接） |

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
   - 自动发布并运行数据库迁移，建立 7 张核心表。
   - 自动运行 `AdminTablesSeeder` 创建初始超级管理员账号（`admin` / `admin`）及初始菜单。
   - 自动发布配置文件 `config/blatui-admin.php`、路由文件 `routes/admin.php` 与基础前端资源。
2. **`admin:make [Model/Table]`**
   - 智能读取指定数据表的字段类型、约束与注释。
   - 自动生成绑定对应模型仓库的 Controller、Model（如需）与 Migration（如需）。
   - 预先生成完整的 `grid()`, `form()`, `detail()` 方法。
3. **`admin:create-user`**
   - 在命令行交互式创建新的后台管理员并指定角色。
4. **`admin:publish`**
   - 一键或按标签发布资源（支持参数 `--tag=blatui-admin-views` 等）。

---

## 6. 实施路线图 (Implementation Phases)

- **第一阶段（基础基石与数据管道）**：
  - 构建完整的 `config/blatui-admin.php`。
  - 编写 7 张核心表的迁移文件与 `AdminTablesSeeder`。
  - 完善 `AdminServiceProvider` 的注册、资源加载与 8 大 `blatui-admin-*` 发布标签。
  - 实现 `admin:install` 引导命令。
- **第二阶段（模型与认证中间件）**：
  - 移植 Dcat 的 `Models/`（Administrator, Role, Permission, Menu, OperationLog）。
  - 配置 `admin` Guard 与 `Authenticate`、`Permission`、`OperationLog` 中间件。
  - 构建登录、登出控制器与认证视图。
- **第三阶段（BlatUI 前端底座与主布局）**：
  - 引入 BlatUI 核心组件（Button, Table, Card, Dialog, Sonner, Dropdown, Sidebar）。
  - 搭建后台全局主布局（侧边栏动态菜单树、顶部栏、暗黑模式切换、Sonner Toast 桥接）。
- **第四阶段（Grid / Form / Show DSL 引擎对接）**：
  - 移植 Grid 核心与仓库层，对接 BlatUI Table 与 Alpine 交互。
  - 移植 Form 核心，对接各类 BlatUI 输入控件，支持前后端数据验证与生命周期保存。
  - 实现基于 Fetch API + Sonner 的异步行操作与删除确认。
- **第五阶段（Scaffold 代码生成器与系统内置页面）**：
  - 移植代码生成器，支持 `admin:make` 自动化一键生成。
  - 实现系统内置管理页面：用户、角色、权限、菜单树与操作日志。
- **第六阶段（自动化测试与质量加固）**：
  - 编写 Pest 测试（覆盖 Provider 绑定、命令、发布标签、认证流与 CRUD 行为）。
  - 执行 `composer test`（PHPStan、Pint、Type Coverage 100% 验证）。
