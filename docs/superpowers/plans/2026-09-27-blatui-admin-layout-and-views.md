# BlatUI Admin Layout & Views Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the modern BlatUI-based Admin Layout Engine (`Content`, `Row`, `Column`, `Navbar`), bundle core BlatUI Blade components, build the responsive admin master layout with dynamic sidebar menu and Sonner flash notifications, and deliver high-aesthetic Login and Dashboard views.

**Architecture:** Port Dcat's elegant `Content` layout builder (`$content->title()->body(...)`) into `BlatUI\Admin\Layout`, implementing `Responsable` and `Htmlable` to seamlessly render directly from controllers. Bundle BlatUI's shadcn-styled Blade components (`button`, `input`, `card`, `sidebar`, `sonner`, `dropdown`, `avatar`, `breadcrumb`, `dialog`). Render the admin sidebar dynamically from `admin_menu` with role permission filtering, Tailwind CSS v4 styling, and Alpine.js reactivity.

**Tech Stack:** PHP ^8.3, Laravel ^12.0 || ^13.0, Blade, Alpine.js, Tailwind CSS v4, Pest, PHPStan/Larastan, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-09-27-blatui-admin-design.md`

## Global Constraints

- Package namespace must be strictly `BlatUI\Admin`.
- View namespace must be `blatui-admin` (e.g. `blatui-admin::layouts.app`).
- Zero jQuery, zero Bootstrap, zero Livewire runtime dependencies.
- Frontend micro-interactions must rely exclusively on Alpine.js directives and Tailwind CSS v4 variables.
- Strictly adhere to PHP 8.3+ with `declare(strict_types=1);` and 100% type coverage.
- Code style must pass `vendor/bin/pint --test` and analysis must pass `vendor/bin/phpstan analyse`.

## Review Focus

- **Content Responsable Contract**: Controllers returning `Content` must automatically render HTTP 200 responses with HTML layout.
- **Dynamic Menu Authorization**: Menu items must hide if the authenticated admin lacks matching roles/permissions.
- **Dark Mode CSS Variables**: Master layout must include theme classes supporting dark mode toggling via Alpine `x-data`.
- **Session Flash to Sonner Bridge**: Session messages (`session('success')`, `session('error')`) must automatically emit Sonner toasts.
- **Responsive Layout Collapsibility**: Sidebar toggle must handle both desktop mini-rail and mobile slide-over drawers via Alpine.js.

---

### Task 1: BlatUI Core Blade UI Components

**Files:**
- Create: `resources/views/components/ui/button.blade.php`
- Create: `resources/views/components/ui/input.blade.php`
- Create: `resources/views/components/ui/card.blade.php`
- Create: `resources/views/components/ui/badge.blade.php`
- Create: `resources/views/components/ui/avatar.blade.php`
- Create: `resources/views/components/ui/separator.blade.php`
- Create: `resources/views/components/ui/sonner.blade.php`
- Create: `resources/views/components/ui/dropdown.blade.php`
- Test: `tests/Feature/ComponentsTest.php`

**Interfaces:**
- Consumes: Blade template engine, Tailwind CSS classes
- Produces: `<x-blatui-admin::ui.*>` components callable in all views.

- [ ] **Step 1: Write the failing test for BlatUI components**

```php
// tests/Feature/ComponentsTest.php
test('blatui core components can be rendered', function () {
    $button = $this->blade('<x-blatui-admin::ui.button>Click Me</x-blatui-admin::ui.button>');
    expect((string) $button)->toContain('Click Me', 'button');

    $card = $this->blade('<x-blatui-admin::ui.card title="Overview">Card Content</x-blatui-admin::ui.card>');
    expect((string) $card)->toContain('Overview', 'Card Content');

    $badge = $this->blade('<x-blatui-admin::ui.badge variant="success">Active</x-blatui-admin::ui.badge>');
    expect((string) $badge)->toContain('Active');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/ComponentsTest.php`
Expected: FAIL with component not found or view not found.

- [ ] **Step 3: Implement core BlatUI components**

Create standard shadcn/BlatUI Blade components under `resources/views/components/ui/`:
1. `button.blade.php`: variants (default, outline, ghost, destructive, secondary), sizes (sm, md, lg).
2. `input.blade.php`: text/password/email styling with focus rings.
3. `card.blade.php`: header, title, body, footer slots.
4. `badge.blade.php`: semantic tones (default, secondary, destructive, outline, success).
5. `avatar.blade.php`: image + text fallback.
6. `separator.blade.php`: horizontal / vertical rule.
7. `sonner.blade.php`: toast container listening to window events.
8. `dropdown.blade.php`: Alpine-powered dropdown popover.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/ComponentsTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/ui/ tests/Feature/ComponentsTest.php
git commit -m "feat(ui): add core BlatUI Blade components"
```

---

### Task 2: Layout Engine (`Content`, `Row`, `Column`, `Navbar`)

**Files:**
- Create: `src/Layout/Content.php`
- Create: `src/Layout/Row.php`
- Create: `src/Layout/Column.php`
- Create: `src/Layout/Navbar.php`
- Test: `tests/Unit/ContentLayoutTest.php`

**Interfaces:**
- Consumes: `Illuminate\Contracts\Support\Responsable`, `Htmlable`, `Renderable`
- Produces: Fluent layout building in controllers (`$content->title('Users')->row(...)`).

- [ ] **Step 1: Write the failing test for Content layout**

```php
// tests/Unit/ContentLayoutTest.php
use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Layout\Row;

test('content builder manages title, description and rows', function () {
    $content = new Content();
    $content->title('User Management')
        ->description('List of users')
        ->row('<div>Row 1</div>')
        ->row(function (Row $row) {
            $row->column(6, '<div>Col 1</div>');
            $row->column(6, '<div>Col 2</div>');
        });

    expect($content->getTitle())->toBe('User Management')
        ->and($content->getDescription())->toBe('List of users')
        ->and($content->getRows())->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ContentLayoutTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement Content, Row, Column, Navbar**

1. Create `src/Layout/Column.php`: width (1-12 columns), content items, HTML rendering.
2. Create `src/Layout/Row.php`: collection of columns or raw HTML elements, grid flex wrapper.
3. Create `src/Layout/Navbar.php`: navbar items, user profile menu, dark mode toggle.
4. Create `src/Layout/Content.php`: implements `Responsable` and `Htmlable`. Renders `blatui-admin::layouts.app` passing title, description, breadcrumbs, and rows.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ContentLayoutTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Layout/ tests/Unit/ContentLayoutTest.php
git commit -m "feat(layout): implement Content, Row, Column and Navbar layout engine"
```

---

### Task 3: Dynamic Menu Tree for Sidebar

**Files:**
- Create: `src/Layout/Menu.php`
- Create: `resources/views/partials/sidebar-menu.blade.php`
- Test: `tests/Feature/SidebarMenuTest.php`

**Interfaces:**
- Consumes: `BlatUI\Admin\Models\Menu`, `BlatUI\Admin\Models\Administrator`
- Produces: Authorized hierarchical sidebar menu tree with icons and active state.

- [ ] **Step 1: Write the failing test for Sidebar menu rendering**

```php
// tests/Feature/SidebarMenuTest.php
use BlatUI\Admin\Layout\Menu as MenuBuilder;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('sidebar menu renders hierarchical items with active states', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $admin = Administrator::where('username', 'admin')->first();
    $this->actingAs($admin, 'admin');

    $menu = new MenuBuilder();
    $tree = $menu->toTree();

    expect($tree)->not->toBeEmpty();
    expect($tree[0]['title'])->toBe('Dashboard');
    expect($tree[1]['title'])->toBe('Admin');
    expect($tree[1]['children'])->toHaveCount(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/SidebarMenuTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement MenuBuilder and sidebar partial**

1. Implement `src/Layout/Menu.php`: fetches `Menu::query()->orderBy('order')`, builds nested tree, filters items by current administrator's roles, and checks active route URI.
2. Create `resources/views/partials/sidebar-menu.blade.php`: renders nested Alpine.js accordion for sub-menus with lucide icon placeholders and active badge/highlighting.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/SidebarMenuTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Layout/Menu.php resources/views/partials/ tests/Feature/SidebarMenuTest.php
git commit -m "feat(layout): implement dynamic sidebar menu tree with authorization"
```

---

### Task 4: Global Admin Master Layout (`resources/views/layouts/app.blade.php`)

**Files:**
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/partials/header.blade.php`
- Create: `resources/views/partials/sidebar.blade.php`
- Test: `tests/Feature/AdminLayoutTest.php`

**Interfaces:**
- Consumes: `Content`, `Menu`, Tailwind CSS v4, Alpine.js
- Produces: Full admin page shell (Sidebar, Header, Main content, Flash toasts, Dark mode).

- [ ] **Step 1: Write the failing test for master layout**

```php
// tests/Feature/AdminLayoutTest.php
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('authenticated admin can access dashboard with master layout', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $admin = Administrator::where('username', 'admin')->first();

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    $response->assertStatus(200);
    $response->assertSee('BlatUI Admin');
    $response->assertSee('Dashboard');
    $response->assertSee('Administrator');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/AdminLayoutTest.php`
Expected: FAIL because dashboard view does not yet use master layout.

- [ ] **Step 3: Implement master layout and partials**

1. Create `resources/views/partials/header.blade.php`: sidebar toggle button, breadcrumb trail, dark mode toggle button (via Alpine), user avatar + dropdown menu with profile and logout form.
2. Create `resources/views/partials/sidebar.blade.php`: logo branding, search/filter, includes `partials/sidebar-menu.blade.php`.
3. Create `resources/views/layouts/app.blade.php`:
   - HTML5 structure with CDN/asset hooks for Tailwind CSS v4 and Alpine.js.
   - Alpine `x-data="{ sidebarOpen: true, mobileSidebarOpen: false, darkMode: false }"` with localStorage persistence.
   - Sonner toast container receiving flash messages (`session('success')`, `session('error')`).
   - Slot/yield for `$content` body.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/AdminLayoutTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/ resources/views/partials/ tests/Feature/AdminLayoutTest.php
git commit -m "feat(views): implement responsive admin master layout shell"
```

---

### Task 5: BlatUI Admin Login and Dashboard Views

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `src/Http/Controllers/DashboardController.php` (return Content layout)
- Test: `tests/Feature/LoginDashboardViewTest.php`

**Interfaces:**
- Consumes: `<x-blatui-admin::ui.*>`, `Content`, `AdminController`
- Produces: Modern login interface and dashboard overview with stat cards.

- [ ] **Step 1: Write the failing test for enhanced login and dashboard**

```php
// tests/Feature/LoginDashboardViewTest.php
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('login page renders blatui card and fields', function () {
    $response = $this->get('/admin/auth/login');
    $response->assertStatus(200);
    $response->assertSee('BlatUI Admin');
    $response->assertSee('Remember me');
});

test('dashboard renders content with stat metric cards', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
    $admin = Administrator::where('username', 'admin')->first();

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    $response->assertStatus(200);
    $response->assertSee('Overview');
    $response->assertSee('Total Administrators');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/LoginDashboardViewTest.php`
Expected: FAIL due to missing cards or elements.

- [ ] **Step 3: Enhance Login & Dashboard with BlatUI components**

1. Update `resources/views/auth/login.blade.php` using `<x-blatui-admin::ui.card>`, `<x-blatui-admin::ui.input>`, `<x-blatui-admin::ui.button>`, error alerts, and "Remember me" checkbox.
2. Update `DashboardController.php` to build layout via `Content::make()` with KPI stat cards (Total Administrators, Roles, Menus) and welcome card.
3. Update `resources/views/dashboard.blade.php` accordingly.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/LoginDashboardViewTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/ src/Http/Controllers/DashboardController.php tests/Feature/LoginDashboardViewTest.php
git commit -m "feat(views): enhance login and dashboard with BlatUI design"
```

---

### Task 6: Full Suite Verification & Quality Gate

**Files:**
- Modify: Any files needing linting/type adjustments.
- Test: Full validation suite.

- [ ] **Step 1: Run static analysis**
Run: `composer analyse`
Expected: PASS with 0 errors.

- [ ] **Step 2: Run code formatting**
Run: `composer lint:check`
Expected: PASS with 0 style issues.

- [ ] **Step 3: Run full Pest test suite with 100% type coverage**
Run: `composer test`
Expected: PASS (Analyse, Lint check, Test types 100%, and Test unit all green).

- [ ] **Step 4: Commit**
```bash
git add .
git commit -m "chore: verify layout and views test suite and code quality"
```
