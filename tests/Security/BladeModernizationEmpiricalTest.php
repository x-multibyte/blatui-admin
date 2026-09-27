<?php

declare(strict_types=1);

use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Column;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

/*
|--------------------------------------------------------------------------
| Milestone 2 (R2) Empirical Verification: Blade View Layer Modernization
|--------------------------------------------------------------------------
|
| Tests cover:
| 1. Full Grid render of table.blade.php with {{ $filter }}, {{ $tools }}, {{ $row->cell($column) }}
| 2. Verification that tools, filter, and displayers render as actual HTML DOM elements (not &lt;div, &lt;span, etc.)
| 3. Verification that plain string values with <script> tags in cells are escaped as &lt;script&gt; (no raw XSS, no double-escaping)
| 4. Verification that filter.blade.php renders {{ $field }} as actual HTML DOM elements
| 5. Verification of layouts/app.blade.php rendering with {{ $content }} (DOM preservation for Content, entity escaping for raw scalar string)
| 6. Adversarial edge cases: diverse XSS payloads, special characters, disabled tools/filters, empty rows
*/

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

dataset('blade_xss_payloads', [
    '<script>alert("xss")</script>',
    '"><img src=x onerror=alert(1)>',
    '\' onfocus=\'alert(1)',
    '<svg/onload=alert(1)>',
    '<iframe src="javascript:alert(1)">',
    '"><script>alert(document.domain)</script>',
]);

test('2.1. table.blade.php renders tools, filter, and displayers as actual DOM elements without escaping tags', function () {
    Administrator::where('username', 'admin')->update([
        'name' => 'Super Administrator',
        'avatar' => 'https://example.com/avatar.jpg',
    ]);

    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->resource('/admin/auth/users');

        // Enable filter with fields
        $grid->filter(function (Filter $filter) {
            $filter->equal('id', 'User ID');
            $filter->like('username', 'Account Name');
        });

        // Configure columns with diverse displayers
        $grid->column('id', 'ID')->sortable();
        $grid->column('username', 'Username')->badge('success');
        $grid->column('name', 'Name')->link('https://example.com/user/{id}');
        $grid->column('avatar', 'Avatar')->image(width: 40, height: 40);
        $grid->column('token', 'Token')->display(fn () => 'secret-token-123')->copyable();
        $grid->column('created_at', 'Created At')->datetime('Y-m-d');
    });

    $html = $grid->render();

    // 1. Verify Tools Bar renders as real DOM elements (NOT entity-escaped)
    expect($html)->toContain('<div class="flex flex-wrap items-center justify-between gap-3 mb-4">')
        ->toContain('<a href="/admin/auth/users/create"')
        ->toContain('Create')
        ->toContain('Reload')
        ->not->toContain('&lt;div class=&quot;flex flex-wrap')
        ->not->toContain('&lt;a href=');

    // 2. Verify Filter Form renders as real DOM elements (NOT entity-escaped)
    expect($html)->toContain('<form id="grid_filter')
        ->toContain('name="id"')
        ->toContain('name="username"')
        ->toContain('User ID')
        ->toContain('Account Name')
        ->toContain('<button type="submit"')
        ->not->toContain('&lt;form')
        ->not->toContain('&lt;button type=&quot;submit&quot;');

    // 3. Verify Displayer HTML DOM markup in cells (NOT entity-escaped)
    // Badge
    expect($html)->toContain('<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold')
        ->toContain('bg-emerald-600')
        ->toContain('admin</span>')
        ->not->toContain('&lt;span class=');

    // Link
    expect($html)->toContain('<a href="https://example.com/user/1"')
        ->toContain('Super Administrator</a>')
        ->not->toContain('&lt;a href=');

    // Image
    expect($html)->toContain('<img src="https://example.com/avatar.jpg"')
        ->toContain('width="40"')
        ->toContain('height="40"')
        ->not->toContain('&lt;img');

    // Copyable
    expect($html)->toContain('<div x-data="{ copied: false }"')
        ->toContain('data-copy-value="secret-token-123"')
        ->not->toContain('&lt;div x-data');
});

test('2.2. table.blade.php neutralizes plain string values containing script tags in cells', function (string $xss) {
    Administrator::where('username', 'admin')->update([
        'name' => $xss,
    ]);

    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID');
        // Plain column without displayer
        $grid->column('name', 'Full Name');
    });

    $html = $grid->render();

    // 1. Ensure dangerous raw unescaped script / attack tags are NEVER in the rendered table DOM
    expect($html)->not->toContain('<script>')
        ->not->toContain('<img src=x onerror')
        ->not->toContain('<svg/onload')
        ->not->toContain('<iframe');

    // 2. Ensure value was properly entity-escaped
    $expectedEscaped = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
    expect($html)->toContain($expectedEscaped);

    // 3. Ensure NO double escaping occurred (e.g. &amp;lt;script&amp;gt;)
    expect($html)->not->toContain('&amp;lt;script&amp;gt;')
        ->not->toContain('&amp;quot;')
        ->not->toContain('&amp;#039;');
})->with('blade_xss_payloads');

test('2.3. table.blade.php safely renders displayers when underlying data contains XSS payloads', function (string $xss) {
    Administrator::where('username', 'admin')->update([
        'name' => $xss,
        'avatar' => 'https://example.com/avatar.png'.$xss,
    ]);

    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('name', 'Badge Name')->badge('warning');
        $grid->column('name_link', 'Link Name')->display(fn ($v, $row) => $row->name)->link();
        $grid->column('avatar', 'Avatar')->image();
    });

    $html = $grid->render();

    // DOM tags must remain intact
    expect($html)->toContain('<span class="inline-flex items-center rounded-full border')
        ->toContain('<a href="')
        ->toContain('<img src="https://example.com/avatar.png')
        ->not->toContain('&lt;span class=')
        ->not->toContain('&lt;a href=')
        ->not->toContain('&lt;img src="https://example.com/avatar.png');

    // Raw dangerous attack tags must NOT appear unescaped in DOM
    expect($html)->not->toContain('<script>')
        ->not->toContain('<img src=x onerror')
        ->not->toContain('<svg/onload')
        ->not->toContain('<iframe');
})->with('blade_xss_payloads');

test('2.4. filter.blade.php renders {{ $field }} as actual HTML DOM elements and sanitizes inputs via Filter::render()', function () {
    $filter = new Filter(new Administrator);
    $filter->equal('id', 'Identifier');
    $filter->like('username', 'User Account');
    $filter->gt('status', 'Min Status');

    $html = $filter->render();

    // Verify fields render as actual DOM elements
    expect($html)->toContain('<div class="flex flex-col gap-1.5">')
        ->toContain('Identifier')
        ->toContain('<input')
        ->toContain('name="id"')
        ->toContain('name="username"')
        ->not->toContain('&lt;div class=&quot;flex flex-col')
        ->not->toContain('&lt;label')
        ->not->toContain('&lt;input');
});

test('2.5. filter.blade.php neutralizes XSS in field labels, placeholders and values', function () {
    $xss = '"><script>alert("filter-xss")</script>';

    $filter = new Filter(new Administrator);
    $field = $filter->like('username', $xss)->placeholder($xss);

    // Simulate input value with XSS
    $ref = new ReflectionProperty($field, 'value');
    $ref->setValue($field, $xss);

    $html = $filter->render();

    // Form DOM elements remain intact
    expect($html)->toContain('<form id="grid_filter')
        ->toContain('<div class="flex flex-col gap-1.5">')
        ->toContain('<input')
        ->not->toContain('&lt;input');

    // Dangerous raw unescaped tags neutralized
    expect($html)->not->toContain('<script>alert("filter-xss")</script>');

    // Values and placeholders properly escaped in attributes
    $escaped = htmlspecialchars($xss, ENT_QUOTES, 'UTF-8');
    expect($html)->toContain('value="'.$escaped.'"')
        ->toContain('placeholder="'.$escaped.'"');
});

test('2.6. layouts/app.blade.php renders Content rows as DOM while escaping raw malicious string', function () {
    // 1. Legitimate Content object: layout rows render as real DOM elements
    $content = new Content;
    $content->title('Admin Dashboard');
    $content->row(function ($row) {
        $row->column(12, '<div id="dashboard-widget">Widget Content</div>');
    });

    $renderedHtml = $content->render();

    expect($renderedHtml)->toContain('<div id="dashboard-widget">Widget Content</div>')
        ->not->toContain('&lt;div id=&quot;dashboard-widget&quot;');

    // 2. Adversarial raw string passed directly to app.blade.php as $content
    $maliciousString = '<script>alert("layout-injection")</script>';
    $appViewHtml = view('blatui-admin::layouts.app', [
        'content' => $maliciousString,
        'title' => 'Test Page',
    ])->render();

    // Blade {{ $content }} must neutralize the malicious raw string
    expect($appViewHtml)->not->toContain('<script>alert("layout-injection")</script>')
        ->toContain('&lt;script&gt;alert(&quot;layout-injection&quot;)&lt;/script&gt;');
});

test('2.7. table.blade.php handles disabled filter, disabled tools, and empty rows gracefully', function () {
    // Empty rows table
    $emptyGrid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->model()->where('id', '>', 999999);
        $grid->column('id', 'ID');
        $grid->column('username', 'Username');
        $grid->disableFilter();
        $grid->disableCreateButton();
        $grid->disableRefreshButton();
        $grid->disableFilterButton();
        $grid->disableBatchActions();
    });

    $html = $emptyGrid->render();

    // Filter and batch actions must NOT be rendered
    expect($html)->not->toContain('<form id="grid_filter"')
        ->not->toContain('Batch Actions')
        ->not->toContain('Create')
        // Empty state must be rendered
        ->toContain('No records found');
});

test('2.8. table.blade.php direct view invocation parity with Htmlable grid proxy', function () {
    $row = new Row(['title' => '<b>Notice</b>', 'code' => '<script>1</script>']);
    $col1 = (new Column('title'))->badge();
    $col2 = new Column('code');

    $grid = new Grid(new Administrator);
    // Non-renderable proxy matches how Grid::render() passes grid to view
    $gridProxy = new class($grid)
    {
        public function __construct(protected Grid $grid) {}

        public function __call(string $method, array $args): mixed
        {
            return $this->grid->{$method}(...$args);
        }

        public function __get(string $name): mixed
        {
            return $this->grid->{$name};
        }
    };

    $html = view('blatui-admin::grid.table', [
        'grid' => $gridProxy,
        'rows' => collect([$row]),
        'columns' => collect(['title' => $col1, 'code' => $col2]),
        'tools' => null,
        'filter' => null,
        'paginator' => null,
    ])->render();

    // col1 (badge) has DOM tag intact
    expect($html)->toContain('<span class="inline-flex items-center')
        ->toContain('&lt;b&gt;Notice&lt;/b&gt;')
        ->not->toContain('&lt;span');

    // col2 (plain) is escaped
    expect($html)->toContain('&lt;script&gt;1&lt;/script&gt;')
        ->not->toContain('<script>1</script>')
        ->not->toContain('&amp;lt;script&amp;gt;');
});
