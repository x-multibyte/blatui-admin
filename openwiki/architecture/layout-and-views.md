---
type: concept
title: Layout Engine and Blade Component System
description: Architecture of the Content layout builder, application shell layout, dynamic sidebar navigation, and BlatUI Tailwind v4 + Alpine.js component library.
tags: [architecture, layout, views, blade, components, alpine, tailwind]
verified:
  - by: openwiki/0.6.0
    at: 2026-09-27T09:35:07.392Z
sources:
  - id: openwiki-source-e7e35fdf34d4d50aa934567d
    resource: repo://resources/views/layouts/app.blade.php
  - id: openwiki-source-df01b763747737c884b08111
    resource: repo://src/Layout/Column.php
  - id: openwiki-source-9386eb5ce0d902a9d16bd1a4
    resource: repo://src/Layout/Content.php
  - id: openwiki-source-b5778e6052b9dbb8f873b0e7
    resource: repo://src/Layout/Menu.php
  - id: openwiki-source-f257e59439505fcc3e8cc681
    resource: repo://src/Layout/Navbar.php
  - id: openwiki-source-afe29a441f84866c95783c40
    resource: repo://src/Layout/Row.php
generated: { by: "antigravity", at: "2026-09-27T09:35:07.392Z" }
---

# Layout Engine and Blade Component System

## Architectural Overview

BlatUI Admin features a modular presentation architecture composed of a fluent PHP layout builder, a modern Blade application shell, and a set of shadcn-inspired Blade UI components. The system eliminates legacy jQuery, Pjax, and inline JavaScript dependencies, replacing them with Tailwind CSS v4 design tokens, Alpine.js reactive primitives, and native Web Fetch APIs.

```
+-------------------------------------------------------------+
|                 Content Builder (PHP DSL)                   |
|              (title, description, breadcrumb)               |
+-------------------------------------------------------------+
                               |
                               v
                     +-------------------+
                     |   Row Container   | (12-column grid)
                     +-------------------+
                               |
                               v
                     +-------------------+
                     |   Column Nodes    | (Responsive widths)
                     +-------------------+
                               |
                               v
+-------------------------------------------------------------+
|              resources/views/layouts/app.blade.php          |
|    +--------------------+-----------------------------+    |
|    |      Sidebar       |           Header            |    |
|    | (Dynamic Auth Tree)|  (Navbar items, Dark Mode)  |    |
|    +--------------------+-----------------------------+    |
|    |                                                  |    |
|    |           Content Slot (Grid / Forms)            |    |
|    |                                                  |    |
|    +--------------------------------------------------+    |
+-------------------------------------------------------------+
```

---

## Content Builder Pipeline

The `BlatUI\Admin\Layout\Content` class coordinates page assembly. It implements `Htmlable`, `Renderable`, and `Responsable`, enabling controllers to return a `Content` object directly from action methods.

### Key Capabilities:
- **Metadata Management**: Sets page title (`title()`), subtitle description (`description()`), and breadcrumb trails (`breadcrumb()`).
- **Row & Column Composition**:
  - `row(mixed $content)`: Creates a new `Row` instance.
  - `body(mixed $content)`: Alias for appending content or widgets into rows.
  - `bodyView(string $view, array $data = [])`: Renders a Blade view template into the body stream.
- **Variable Injection**: Passes custom data to the root layout template via `with(array|string $key, mixed $value = null)`.
- **Hierarchical Rendering**: Invokes `renderRows()` across all child `Row` objects, embedding the output into `resources/views/layouts/app.blade.php`.

```php
public function index(Content $content): Content
{
    return $content
        ->title('Dashboard')
        ->description('Overview of metrics')
        ->breadcrumb(['text' => 'Home', 'url' => '/admin'], ['text' => 'Dashboard'])
        ->row(function (Row $row) {
            $row->column(6, new MetricsCard());
            $row->column(6, new SystemStatus());
        });
}
```

---

## Grid Layout Hierarchy: `Row` & `Column`

The layout builder organizes visual elements on a standard 12-column responsive grid:

### `BlatUI\Admin\Layout\Row`
- Encapsulates multiple `Column` instances.
- Applies standard CSS grid classes (`grid grid-cols-12 gap-4 mb-4`).
- Handles shorthand column addition: `$row->column(6, $widget)` automatically wraps content in a 6-unit wide column.

### `BlatUI\Admin\Layout\Column`
- Implements flexible width scaling on a 1-12 scale via `normalizeWidth()`.
- Supports fractional widths (`0.5` normalizes to `6`), integers (`1` through `12`), or responsive breakpoint dictionaries (`['sm' => 12, 'md' => 6]`).
- Appends nested content items, widgets, HTML strings, or closures.

---

## Application Shell & Navigation Shell

The outer shell is declared in `resources/views/layouts/app.blade.php` and its constituent partials:

1. **`layouts/app.blade.php`**:
   - Master HTML document structure.
   - Bootstraps Tailwind CSS v4 and Alpine.js via CDN.
   - Manages global Alpine reactive state for `sidebarCollapsed`, `mobileSidebarOpen`, and persistent `darkMode` with `localStorage` synchronization.
2. **`partials/header.blade.php`**:
   - Sticky top navigation bar.
   - Houses mobile sidebar trigger buttons, breadcrumbs, search bars, user profile dropdowns, and dark mode toggles.
3. **`partials/sidebar.blade.php`**:
   - Left navigation rail displaying the logo/brand header.
   - Hosts dynamic menu navigation rendered through `BlatUI\Admin\Layout\Menu`.

---

## Dynamic Sidebar Menu System

The `BlatUI\Admin\Layout\Menu` builder queries menu entities and renders them through `resources/views/partials/sidebar-menu.blade.php`:

- **Database-Driven**: Queries configured `MenuModel` (`admin_menu` table) ordered by the `order` column.
- **Permission Filtering**: Filters menu items against the currently authenticated administrator (`Auth::guard('admin')->user()`). Nodes lacking permission are pruned along with empty parent containers.
- **Active State Detection**: Automatically matches route patterns against the current HTTP request via `request()->is()`, activating parent menus when child paths match.
- **Recursive Tree Projection**: Generates nested tree structures suitable for Alpine.js expandable sidebar dropdowns.

---

## Navbar Extension Engine

`BlatUI\Admin\Layout\Navbar` provides extension points for injecting custom widgets into the administrative header:
- **Sections**: Supports `left(mixed $element)` and `right(mixed $element)` positioning.
- **Rendering**: Recursively evaluates `Htmlable`, `Renderable`, or stringable elements via `BlatUI\Admin\Support\Helper::render()`.

---

## BlatUI Blade UI Component Library

BlatUI Admin bundles pre-built Blade UI components inspired by shadcn/ui and styled with Tailwind CSS v4 utility classes located under `resources/views/components/ui/`:

- `<x-ui.avatar>`: User profile image with fallback initials.
- `<x-ui.badge>`: Contextual badges supporting color variants (`default`, `secondary`, `destructive`, `outline`, `success`).
- `<x-ui.button>`: Flexible button component supporting sizes (`sm`, `md`, `lg`, `icon`) and button variants (`primary`, `destructive`, `ghost`, `link`).
- `<x-ui.card>`: Container cards with header, title, description, content, and footer sub-slots.
- `<x-ui.dropdown>`: Accessible Alpine.js-powered dropdown menus.
- `<x-ui.input>`: Form text, email, and password input fields.
- `<x-ui.separator>`: Horizontal or vertical divider lines.
- `<x-ui.sonner>`: Toast notification container listening to dispatch events.
