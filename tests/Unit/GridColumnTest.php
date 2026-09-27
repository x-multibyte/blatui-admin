<?php

declare(strict_types=1);

use BlatUI\Admin\Grid\Column;
use BlatUI\Admin\Grid\Displayers\AbstractDisplayer;
use Illuminate\Support\Carbon;

test('grid column supports fluent label, sorting and custom displayers', function () {
    $column = new Column('status', 'Account Status');
    $column->sortable();
    $column->badge('success', [1 => 'Active', 0 => 'Disabled']);

    expect($column->getName())->toBe('status')
        ->and($column->getLabel())->toBe('Account Status')
        ->and($column->isSortable())->toBeTrue();

    $rendered = $column->renderCell(1, ['id' => 1, 'status' => 1]);
    expect($rendered)->toContain('Active');
});

test('grid column supports closures, copyable and datetime displayers', function () {
    $column = new Column('created_at', 'Created');
    $column->datetime('Y-m-d');

    $html = $column->renderCell('2026-09-27 12:00:00', []);
    expect($html)->toContain('2026-09-27');
});

test('grid column auto title-cases label when null and supports custom label', function () {
    $col1 = new Column('created_at');
    expect($col1->getLabel())->toBe('Created At');

    $col2 = new Column('user_profile_id');
    expect($col2->getLabel())->toBe('User Profile Id');

    $col1->label('Date of Creation');
    expect($col1->getLabel())->toBe('Date of Creation');
});

test('grid column supports width, help and sortable configuration', function () {
    $column = new Column('title');

    expect($column->isSortable())->toBeFalse()
        ->and($column->getWidth())->toBeNull()
        ->and($column->getHelp())->toBeNull()
        ->and($column->hasHelp())->toBeFalse();

    $column->sortable()
        ->width(120)
        ->help('Post title tooltip');

    expect($column->isSortable())->toBeTrue()
        ->and($column->getWidth())->toBe(120)
        ->and($column->getHelp())->toBe('Post title tooltip')
        ->and($column->hasHelp())->toBeTrue();

    $column->sortable(false);
    expect($column->isSortable())->toBeFalse();
});

test('grid column supports align and default fallback', function () {
    $column = new Column('amount');
    expect($column->getAlign())->toBe('left')
        ->and($column->getDefault())->toBeNull();

    $column->align('right')->default('0.00');
    expect($column->getAlign())->toBe('right')
        ->and($column->getDefault())->toBe('0.00');

    expect($column->renderCell(null, []))->toBe('0.00');
    expect($column->renderCell('', []))->toBe('0.00');
    expect($column->renderCell('12.50', []))->toBe('12.50');
});

test('grid column extracts value from row when value is null', function () {
    $column = new Column('title');

    $rendered = $column->renderCell(null, ['title' => 'Laravel News']);
    expect($rendered)->toBe('Laravel News');

    $obj = (object) ['title' => 'BlatUI Release'];
    $renderedObj = $column->renderCell(null, $obj);
    expect($renderedObj)->toBe('BlatUI Release');
});

test('grid column executes custom closures in pipeline with bound row', function () {
    $column = new Column('score');
    $column->display(fn ($v) => (int) $v * 2)
        ->display(fn ($v) => "Score: {$v}");

    expect($column->getCallbacks())->toHaveCount(2);

    $result = $column->renderCell(5, []);
    expect($result)->toBe('Score: 10');

    // Closure using $this with an object row
    $colTitle = new Column('name');
    $colTitle->display(function ($val) {
        return strtoupper((string) $val).' - '.$this->role;
    });

    $rowObj = (object) ['name' => 'Alice', 'role' => 'Administrator'];
    expect($colTitle->renderCell('Alice', $rowObj))->toBe('ALICE - Administrator');
});

test('grid column handles complex types in renderCell', function () {
    $column = new Column('meta');

    // Array value
    expect($column->renderCell(['tag' => 'tech']))->toBe('{"tag":"tech"}');

    // Stringable value
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'stringable-output';
        }
    };
    expect($column->renderCell($stringable))->toBe('stringable-output');

    // Generic object without __toString
    $std = new stdClass;
    $std->foo = 'bar';
    expect($column->renderCell($std))->toBe('{"foo":"bar"}');
});

test('badge displayer supports variants, mappings and empty fallback', function () {
    $col = new Column('status');
    $col->badge('success');

    $html = $col->renderCell('active', []);
    expect($html)->toContain('bg-emerald-600')
        ->and($html)->toContain('active');

    // Variant mapping
    $col2 = new Column('role');
    $col2->badge('default', ['admin' => 'Admin Role', 'user' => 'User Role']);
    expect($col2->renderCell('admin', []))->toContain('Admin Role');

    // Direct variant mapping array
    $col3 = new Column('level');
    $col3->badge(['high' => 'danger', 'low' => 'info']);
    expect($col3->renderCell('high', []))->toContain('bg-red-600');

    // Variant mapping when map values are variant names
    $col4 = new Column('state');
    $col4->badge('default', [1 => 'success', 0 => 'danger']);
    expect($col4->renderCell(1, []))->toContain('bg-emerald-600')
        ->and($col4->renderCell(1, []))->toContain('1');

    // Empty fallback
    $colEmpty = new Column('empty_col');
    $colEmpty->badge();
    expect($colEmpty->renderCell(null, []))->toBe('');
    expect($colEmpty->renderCell('', []))->toBe('');
});

test('link displayer renders anchor tag with parameters and empty fallback', function () {
    $col = new Column('url');
    $col->link();
    expect($col->renderCell('https://example.com', []))
        ->toContain('href="https://example.com"')
        ->toContain('https://example.com');

    // Link with row placeholder template
    $colUser = new Column('username');
    $colUser->link('/admin/users/{id}', '_blank');
    $rendered = $colUser->renderCell('john_doe', ['id' => 42]);

    expect($rendered)
        ->toContain('href="/admin/users/42"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('john_doe');

    // Link with closure href
    $colClosure = new Column('slug');
    $colClosure->link(fn ($val, $row) => "/posts/{$val}");
    expect($colClosure->renderCell('first-post', []))->toContain('href="/posts/first-post"');

    // Empty fallback
    $emptyCol = new Column('empty_url');
    $emptyCol->link();
    expect($emptyCol->renderCell(null, []))->toBe('');
});

test('copyable displayer renders alpine copy trigger and empty fallback', function () {
    $col = new Column('api_token');
    $col->copyable();

    $html = $col->renderCell('secret-token-123', []);
    expect($html)->toContain('x-data=')
        ->and($html)->toContain('navigator.clipboard.writeText')
        ->and($html)->toContain('secret-token-123');

    // Empty fallback
    $colEmpty = new Column('empty_token');
    $colEmpty->copyable();
    expect($colEmpty->renderCell(null, []))->toBe('');
});

test('image displayer renders img tag with dimensions and empty fallback', function () {
    $col = new Column('avatar');
    $col->image('https://cdn.example.com/uploads', 48, 48);

    $html = $col->renderCell('user.png', []);
    expect($html)->toContain('<img')
        ->and($html)->toContain('src="https://cdn.example.com/uploads/user.png"')
        ->and($html)->toContain('width="48"')
        ->and($html)->toContain('height="48"');

    // Full url without server prefix
    $colFull = new Column('photo');
    $colFull->image();
    expect($colFull->renderCell('https://placehold.co/100', []))
        ->toContain('src="https://placehold.co/100"');

    // Multiple images array
    $colMulti = new Column('gallery');
    $colMulti->image(null, 64, 64);
    $multiHtml = $colMulti->renderCell(['/img1.png', '/img2.png'], []);
    expect($multiHtml)->toContain('src="/img1.png"')
        ->and($multiHtml)->toContain('src="/img2.png"');

    // Empty fallback
    $colEmpty = new Column('empty_avatar');
    $colEmpty->image();
    expect($colEmpty->renderCell(null, []))->toBe('');
});

test('datetime displayer formats carbon, strings, timestamps and handles invalid dates gracefully', function () {
    $col = new Column('created_at');
    $col->datetime('Y/m/d');

    // String date
    expect($col->renderCell('2026-09-27 15:30:00', []))->toBe('2026/09/27');

    // Carbon instance
    $carbon = Carbon::create(2026, 9, 27, 8, 0, 0);
    expect($col->renderCell($carbon, []))->toBe('2026/09/27');

    // Unix timestamp
    $timestamp = $carbon->getTimestamp();
    expect($col->renderCell($timestamp, []))->toBe('2026/09/27');

    // Invalid date string fallback gracefully without error
    $colInvalid = new Column('invalid_date');
    $colInvalid->datetime('Y-m-d');
    expect($colInvalid->renderCell('not-a-valid-date', []))->toBe('not-a-valid-date');

    // Empty fallback
    expect($col->renderCell(null, []))->toBe('');
    expect($col->renderCell('', []))->toBe('');
});

test('using displayer maps dictionary values and supports default', function () {
    $col = new Column('gender');
    $col->using(['m' => 'Male', 'f' => 'Female'], 'Unknown');

    expect($col->renderCell('m', []))->toBe('Male')
        ->and($col->renderCell('f', []))->toBe('Female')
        ->and($col->renderCell('x', []))->toBe('Unknown');

    // Without explicit default returns original value if key not found
    $colNoDefault = new Column('type');
    $colNoDefault->using([1 => 'Standard']);
    expect($colNoDefault->renderCell(2, []))->toBe('2');

    // Empty fallback returns default
    expect($col->renderCell(null, []))->toBe('Unknown');
});

test('limit displayer truncates strings and handles empty fallback', function () {
    $col = new Column('bio');
    $col->limit(10, '...');

    expect($col->renderCell('Hello World This Is Long', []))->toBe('Hello Worl...');
    expect($col->renderCell('Short', []))->toBe('Short');

    // Empty fallback
    expect($col->renderCell(null, []))->toBe('');
    expect($col->renderCell('', []))->toBe('');
});

test('abstract displayer provides accessors to column, value and row', function () {
    $column = new Column('test_col');
    $row = ['id' => 99, 'test_col' => 'hello'];

    $customDisplayer = new class($column, 'hello', $row) extends AbstractDisplayer
    {
        public function display(): string
        {
            return sprintf(
                'col=%s,val=%s,row_id=%s',
                $this->getColumn()->getName(),
                (string) $this->getValue(),
                (string) data_get($this->getRow(), 'id'),
            );
        }
    };

    expect($customDisplayer->display())->toBe('col=test_col,val=hello,row_id=99');
});

test('displayers can be chained together in pipeline', function () {
    $column = new Column('status');
    $column->using([1 => 'active', 0 => 'inactive'])
        ->badge('success');

    $html = $column->renderCell(1, ['id' => 1, 'status' => 1]);
    expect($html)->toContain('active')
        ->and($html)->toContain('bg-emerald-600');
});

test('all 7 built-in displayers work with object $row without closure rebinding errors', function () {
    $row = (object) [
        'id' => 42,
        'status' => 1,
        'url' => 'https://example.com/item/42',
        'token' => 'token-xyz',
        'avatar' => 'user-42.png',
        'created_at' => '2026-09-27 10:00:00',
        'role' => 'admin',
        'bio' => 'A long bio description that will be truncated by limit',
    ];

    // 1. Badge with object row
    $colBadge = new Column('status');
    $colBadge->badge('success', [1 => 'Active']);
    $htmlBadge = $colBadge->renderCell(null, $row);
    expect($htmlBadge)->toContain('Active')
        ->and($htmlBadge)->toContain('bg-emerald-600');

    // 2. Link with object row
    $colLink = new Column('id');
    $colLink->link('/admin/items/{id}');
    $htmlLink = $colLink->renderCell(null, $row);
    expect($htmlLink)->toContain('href="/admin/items/42"')
        ->and($htmlLink)->toContain('42');

    // 3. Copyable with object row
    $colCopy = new Column('token');
    $colCopy->copyable();
    $htmlCopy = $colCopy->renderCell(null, $row);
    expect($htmlCopy)->toContain('token-xyz')
        ->and($htmlCopy)->toContain('x-data=');

    // 4. Image with object row
    $colImg = new Column('avatar');
    $colImg->image('https://cdn.example.com', 40, 40);
    $htmlImg = $colImg->renderCell(null, $row);
    expect($htmlImg)->toContain('src="https://cdn.example.com/user-42.png"')
        ->and($htmlImg)->toContain('width="40"');

    // 5. Datetime with object row
    $colDate = new Column('created_at');
    $colDate->datetime('Y-m-d');
    $htmlDate = $colDate->renderCell(null, $row);
    expect($htmlDate)->toBe('2026-09-27');

    // 6. Using with object row
    $colUsing = new Column('role');
    $colUsing->using(['admin' => 'Administrator']);
    $htmlUsing = $colUsing->renderCell(null, $row);
    expect($htmlUsing)->toBe('Administrator');

    // 7. Limit with object row
    $colLimit = new Column('bio');
    $colLimit->limit(10, '...');
    $htmlLimit = $colLimit->renderCell(null, $row);
    expect($htmlLimit)->toBe('A long bio...');
});

test('displayers safely handle non-scalar and array cell values without warnings or type errors', function () {
    // Badge with array of tags
    $colBadgeArray = new Column('tags');
    $colBadgeArray->badge('info');
    $badgeOutput = $colBadgeArray->renderCell(['php', 'laravel'], []);
    expect($badgeOutput)->toContain('php')
        ->and($badgeOutput)->toContain('laravel')
        ->and($badgeOutput)->toContain('bg-sky-500');

    // Using with array value
    $colUsingArray = new Column('metadata');
    $colUsingArray->using(['foo' => 'bar'], 'Fallback');
    expect($colUsingArray->renderCell(['foo'], []))->toBe('Fallback');

    // Using with object value
    $colUsingObj = new Column('obj');
    $colUsingObj->using(['foo' => 'bar'], 'FallbackObj');
    expect($colUsingObj->renderCell((object) ['k' => 'v'], []))->toBe('FallbackObj');

    // Copyable with array value
    $colCopyArray = new Column('config');
    $colCopyArray->copyable();
    $copyOutput = $colCopyArray->renderCell(['key' => 'secret'], []);
    expect($copyOutput)->toContain('secret')
        ->and($copyOutput)->toContain('x-data=');

    // Limit with array value
    $colLimitArray = new Column('items');
    $colLimitArray->limit(10);
    expect($colLimitArray->renderCell(['a' => 'b'], []))->toBe('{"a":"b"}');

    // Datetime with array value
    $colDateArray = new Column('invalid_date');
    $colDateArray->datetime('Y-m-d');
    expect($colDateArray->renderCell(['2026-09-27'], []))->toBe('');

    // Link with array value
    $colLinkArray = new Column('links');
    $colLinkArray->link();
    expect($colLinkArray->renderCell(['url1'], []))->toContain('url1');
});
