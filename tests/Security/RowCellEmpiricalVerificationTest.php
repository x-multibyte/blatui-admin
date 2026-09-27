<?php

declare(strict_types=1);

use BlatUI\Admin\Grid\Column;
use BlatUI\Admin\Grid\Row;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable as SupportStringable;

/*
|--------------------------------------------------------------------------
| Milestone 1 (R1) Empirical Verification: Row::cell() & Column Rendering
|--------------------------------------------------------------------------
|
| Tests cover:
| 1. Displayer DOM markup preservation (badge, link, copyable, image, datetime, using, limit)
| 2. Raw XSS payload entity-escaping
| 3. Type coercion (null, boolean, integer 0/negative, float, objects, arrays)
| 4. Blade e($row->cell($column)) and Blade template rendering parity
|    (no double-escaping of displayer markup or pre-escaped entities)
| 5. Contract compliance and View::gatherData() interaction analysis
*/

dataset('xss_payloads', [
    '<script>alert("xss")</script>',
    '"><img src=x onerror=alert(1)>',
    '\' onfocus=\'alert(1)',
    '<svg/onload=alert(1)>',
    '<iframe src="javascript:alert(1)">',
    'javascript:alert(document.cookie)',
    '<a href="javascript:alert(1)">click me</a>',
    '"><script>alert(document.domain)</script>',
]);

test('1.1. badge displayer preserves DOM span markup and safely escapes value', function (string $xss) {
    $row = new Row(['status' => $xss]);
    $column = (new Column('status'))->badge('success');

    $cell = $row->cell($column);

    expect($cell)->toBeInstanceOf(HtmlString::class);

    $html = $cell->toHtml();

    // DOM markup is intact (not entity-escaped into &lt;span)
    expect($html)->toStartWith('<span class="')
        ->toContain('bg-emerald-600')
        ->toEndWith('</span>')
        ->not->toContain('&lt;span');

    // XSS payload is strictly neutralized
    expect($html)->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('<svg')
        ->not->toContain('<iframe');

    // Blade e() helper does NOT double-escape displayer DOM markup or contents
    $bladeEscaped = e($cell);
    expect($bladeEscaped)->toBe($html)
        ->and($bladeEscaped)->toStartWith('<span class="')
        ->and($bladeEscaped)->not->toContain('&lt;span');

    // Blade template loop rendering matches exactly
    $bladeRendered = Blade::render(
        '@foreach($rows as $row) {{ $row->cell($col) }} @endforeach',
        ['rows' => collect([$row]), 'col' => $column],
    );
    expect(trim($bladeRendered))->toBe($html);
})->with('xss_payloads');

test('1.2. badge displayer handles array of values with DOM preservation', function () {
    $row = new Row(['tags' => ['admin', '<script>alert(1)</script>', 'moderator']]);
    $column = (new Column('tags'))->badge('info');

    $cell = $row->cell($column);
    $html = $cell->toHtml();

    expect($html)->toContain('<span class="')
        ->toContain('admin')
        ->toContain('moderator')
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');

    // Blade e() doesn't double-escape
    expect(e($cell))->toBe($html);
});

test('1.3. link displayer preserves anchor DOM tag and escapes href and text', function (string $xss) {
    $row = new Row(['url' => $xss, 'id' => 42]);
    $column = (new Column('url'))->link(href: '{url}', target: '_blank');

    $cell = $row->cell($column);
    $html = $cell->toHtml();

    // DOM tag preserved
    expect($html)->toStartWith('<a href="')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toEndWith('</a>');

    // XSS payload in href and body is escaped — dangerous raw tags never appear unescaped
    expect($html)->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('<iframe')
        ->not->toContain('<svg/onload');

    // Blade e() matches exactly
    expect(e($cell))->toBe($html);

    $bladeRendered = Blade::render(
        '@foreach($rows as $row) {{ $row->cell($col) }} @endforeach',
        ['rows' => collect([$row]), 'col' => $column],
    );
    expect(trim($bladeRendered))->toBe($html);
})->with('xss_payloads');

test('1.4. copyable displayer preserves container, button, and svg DOM while escaping content', function (string $xss) {
    $row = new Row(['token' => $xss]);
    $column = (new Column('token'))->copyable();

    $cell = $row->cell($column);
    $html = $cell->toHtml();

    // DOM preserved
    expect($html)->toContain('<div x-data="{ copied: false }"')
        ->toContain('<button type="button"')
        ->toContain('<svg')
        ->toContain('data-copy-value=');

    // XSS escaped inside data-copy-value and span text
    expect($html)->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('<svg/onload=')
        ->not->toContain('<iframe');

    // Blade e() matches
    expect(e($cell))->toBe($html);

    $bladeRendered = Blade::render(
        '@foreach($rows as $row) {{ $row->cell($col) }} @endforeach',
        ['rows' => collect([$row]), 'col' => $column],
    );
    expect(trim($bladeRendered))->toBe($html);
})->with('xss_payloads');

test('1.5. image displayer preserves img DOM tag and escapes src attributes', function () {
    $xssUrl = 'https://example.com/avatar.jpg"><script>alert(1)</script>';
    $row = new Row(['avatar' => $xssUrl]);
    $column = (new Column('avatar'))->image(width: 48, height: 48);

    $cell = $row->cell($column);
    $html = $cell->toHtml();

    // DOM tag preserved
    expect($html)->toStartWith('<img src="')
        ->toContain('width="48"')
        ->toContain('height="48"')
        ->not->toContain('&lt;img');

    // XSS payload escaped inside src attribute
    expect($html)->not->toContain('"><script>')
        ->toContain('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;');

    // Blade e() matches
    expect(e($cell))->toBe($html);

    $bladeRendered = Blade::render(
        '@foreach($rows as $row) {{ $row->cell($col) }} @endforeach',
        ['rows' => collect([$row]), 'col' => $column],
    );
    expect(trim($bladeRendered))->toBe($html);
});

test('1.6. image displayer supports multiple image thumbnails', function () {
    $row = new Row(['photos' => [
        'https://example.com/1.png',
        'https://example.com/2.png',
    ]]);
    $column = (new Column('photos'))->image();

    $cell = $row->cell($column);
    $html = $cell->toHtml();

    expect(substr_count($html, '<img src="'))->toBe(2);
    expect(e($cell))->toBe($html);
});

test('1.7. datetime displayer formats dates safely and escapes invalid input', function () {
    // Valid Carbon date
    $now = Carbon::create(2026, 9, 27, 12, 30, 0);
    $row = new Row(['created_at' => $now]);
    $col1 = (new Column('created_at'))->datetime('Y-m-d H:i');

    expect($row->cell($col1)->toHtml())->toBe('2026-09-27 12:30')
        ->and(e($row->cell($col1)))->toBe('2026-09-27 12:30');

    // Valid timestamp
    $rowTs = new Row(['created_at' => $now->timestamp]);
    expect($rowTs->cell($col1)->toHtml())->toBe('2026-09-27 12:30');

    // XSS in datetime string fallback
    $xssRow = new Row(['created_at' => '2026-09-27 <script>alert(1)</script>']);
    $cellXss = $xssRow->cell($col1);
    expect($cellXss->toHtml())->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and(e($cellXss))->toBe($cellXss->toHtml());
});

test('1.8. using displayer maps dictionary and escapes HTML in map and unmapped values', function () {
    $row = new Row(['status' => 'pending', 'xss' => '<script>alert(1)</script>']);

    // Mapped value
    $colMap = (new Column('status'))->using([
        'pending' => '<span class="text-yellow">Pending Review</span>',
        'active' => 'Active',
    ]);
    $cellMap = $row->cell($colMap);
    // Displayer htmlspecialchars-escapes map values to prevent XSS injection via map definitions
    expect($cellMap->toHtml())->toBe('&lt;span class=&quot;text-yellow&quot;&gt;Pending Review&lt;/span&gt;')
        ->and(e($cellMap))->toBe($cellMap->toHtml());

    // Unmapped fallback value with XSS
    $colUnmapped = (new Column('xss'))->using(['foo' => 'bar']);
    $cellUnmapped = $row->cell($colUnmapped);
    expect($cellUnmapped->toHtml())->toBe('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and(e($cellUnmapped))->toBe($cellUnmapped->toHtml());
});

test('1.9. limit displayer truncates strings and entity-escapes output', function () {
    $longXss = '<script>alert("long-xss-payload")</script> extra long text exceeding limit';
    $row = new Row(['bio' => $longXss]);
    $column = (new Column('bio'))->limit(20);

    $cell = $row->cell($column);
    $html = $cell->toHtml();

    expect($html)->not->toContain('<script>')
        ->toContain('&lt;script&gt;')
        ->toEndWith('...')
        ->and(e($cell))->toBe($html);
});

test('2.1. plain column cells with raw XSS payloads are safely escaped without displayers', function (string $xss) {
    $row = new Row(['content' => $xss]);
    $column = new Column('content');

    $cell = $row->cell($column);

    expect($cell)->toBeInstanceOf(HtmlString::class);

    $html = $cell->toHtml();

    // Raw dangerous tags must never be present unescaped
    expect($html)->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('<svg')
        ->not->toContain('<iframe')
        ->not->toContain('<a ');

    // Must match htmlspecialchars output
    expect($html)->toBe(htmlspecialchars($xss, ENT_QUOTES, 'UTF-8'));

    // Blade e() and Blade rendering must NOT double-escape (&amp;lt;)
    $bladeEscaped = e($cell);
    expect($bladeEscaped)->toBe($html)
        ->and($bladeEscaped)->not->toContain('&amp;lt;')
        ->and($bladeEscaped)->not->toContain('&amp;quot;')
        ->and($bladeEscaped)->not->toContain('&amp;#039;');

    $bladeRendered = Blade::render(
        '@foreach($rows as $row) {{ $row->cell($col) }} @endforeach',
        ['rows' => collect([$row]), 'col' => $column],
    );
    expect(trim($bladeRendered))->toBe($html);
})->with('xss_payloads');

test('3.1. type coercion: null and missing keys render empty string or default fallback', function () {
    $rowNull = new Row(['name' => null]);
    $rowMissing = new Row([]);
    $colPlain = new Column('name');

    expect($rowNull->cell($colPlain)->toHtml())->toBe('')
        ->and($rowMissing->cell($colPlain)->toHtml())->toBe('');

    // Default fallback
    $colWithDefault = (new Column('name'))->default('N/A');
    expect($rowNull->cell($colWithDefault)->toHtml())->toBe('N/A')
        ->and($rowMissing->cell($colWithDefault)->toHtml())->toBe('N/A');

    // Default fallback containing XSS
    $colXssDefault = (new Column('name'))->default('<script>alert("default")</script>');
    expect($rowNull->cell($colXssDefault)->toHtml())->toBe('&lt;script&gt;alert(&quot;default&quot;)&lt;/script&gt;')
        ->and(e($rowNull->cell($colXssDefault)))->toBe('&lt;script&gt;alert(&quot;default&quot;)&lt;/script&gt;');
});

test('3.2. type coercion: integer zero, positive, and negative values render correctly', function () {
    $rowZero = new Row(['count' => 0]);
    $col = new Column('count');
    // Edge case: 0 must not be treated as empty or null!
    expect($rowZero->cell($col)->toHtml())->toBe('0')
        ->and(e($rowZero->cell($col)))->toBe('0');

    $rowPos = new Row(['count' => 42]);
    expect($rowPos->cell($col)->toHtml())->toBe('42');

    $rowNeg = new Row(['count' => -100]);
    expect($rowNeg->cell($col)->toHtml())->toBe('-100');
});

test('3.3. type coercion: boolean values render string representations', function () {
    $rowTrue = new Row(['flag' => true]);
    $rowFalse = new Row(['flag' => false]);
    $col = new Column('flag');

    expect($rowTrue->cell($col)->toHtml())->toBe('1')
        ->and($rowFalse->cell($col)->toHtml())->toBe('');
});

test('3.4. type coercion: float numbers render string representations', function () {
    $rowFloat = new Row(['price' => 19.99, 'zero_float' => 0.0]);
    $colPrice = new Column('price');
    $colZero = new Column('zero_float');

    expect($rowFloat->cell($colPrice)->toHtml())->toBe('19.99')
        ->and($rowFloat->cell($colZero)->toHtml())->toBe('0');
});

test('3.5. type coercion: Stringable objects are cast to string and escaped', function () {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return '<b>Bold Title</b>';
        }
    };
    $row = new Row(['title' => $stringable]);
    $col = new Column('title');

    expect($row->cell($col)->toHtml())->toBe('&lt;b&gt;Bold Title&lt;/b&gt;')
        ->and(e($row->cell($col)))->toBe('&lt;b&gt;Bold Title&lt;/b&gt;');

    $laravelStringable = new SupportStringable('<script>alert("illuminate")</script>');
    $row2 = new Row(['title' => $laravelStringable]);
    expect($row2->cell($col)->toHtml())->toBe('&lt;script&gt;alert(&quot;illuminate&quot;)&lt;/script&gt;');
});

test('3.6. type coercion: Htmlable objects are preserved as trusted HTML', function () {
    $trusted = new HtmlString('<span class="badge">Safe HTML</span>');
    $row = new Row(['content' => $trusted]);
    $col = new Column('content');

    expect($row->cell($col)->toHtml())->toBe('<span class="badge">Safe HTML</span>')
        ->and(e($row->cell($col)))->toBe('<span class="badge">Safe HTML</span>');
});

test('3.7. type coercion: stdClass and arrays are JSON-encoded and entity-escaped', function () {
    $arrayData = ['role' => 'admin', 'tag' => '<test>'];
    $rowArray = new Row(['meta' => $arrayData]);
    $col = new Column('meta');

    $htmlArray = $rowArray->cell($col)->toHtml();
    expect($htmlArray)->toBe('{&quot;role&quot;:&quot;admin&quot;,&quot;tag&quot;:&quot;&lt;test&gt;&quot;}')
        ->and(e($rowArray->cell($col)))->toBe($htmlArray);

    $obj = new stdClass;
    $obj->user = '<admin>';
    $rowObj = new Row(['meta' => $obj]);
    $htmlObj = $rowObj->cell($col)->toHtml();
    expect($htmlObj)->toBe('{&quot;user&quot;:&quot;&lt;admin&gt;&quot;}')
        ->and(e($rowObj->cell($col)))->toBe($htmlObj);
});

test('4.1. Blade {!! $row->cell($col) !!} and {{ $row->cell($col) }} produce identical safe output', function () {
    $row = new Row([
        'plain_xss' => '<script>alert("xss")</script>',
        'badge_val' => 'Approved',
        'link_val' => 'https://example.com',
    ]);

    $colPlain = new Column('plain_xss');
    $colBadge = (new Column('badge_val'))->badge('success');
    $colLink = (new Column('link_val'))->link();

    foreach ([$colPlain, $colBadge, $colLink] as $column) {
        $raw = Blade::render(
            '@foreach($rows as $row) {!! $row->cell($col) !!} @endforeach',
            ['rows' => collect([$row]), 'col' => $column],
        );
        $escaped = Blade::render(
            '@foreach($rows as $row) {{ $row->cell($col) }} @endforeach',
            ['rows' => collect([$row]), 'col' => $column],
        );

        expect(trim($escaped))->toBe(trim($raw))
            ->and(trim($escaped))->toBe($row->cell($column)->toHtml());
    }
});

test('5.1. Column and Row contract compliance', function () {
    $column = new Column('title', 'Header Title');
    $row = new Row(['id' => 1, 'title' => 'Sample']);

    expect($column)->toBeInstanceOf(Htmlable::class)
        ->and($column)->toBeInstanceOf(Stringable::class)
        ->and($column->toHtml())->toBe('Header Title')
        ->and((string) $column)->toBe('Header Title')
        ->and(e($column))->toBe('Header Title');

    expect($row)->toBeInstanceOf(Htmlable::class)
        ->and($row)->toBeInstanceOf(Stringable::class)
        ->and($row->toHtml())->toBe($row->render())
        ->and((string) $row)->toBe($row->render());
});

test('5.2. View::gatherData() side-effect on Renderable Row objects when passed as top-level view variable', function () {
    $row = new Row(['id' => 1, 'username' => 'admin']);
    $col = new Column('username');

    // When Row implements Renderable, passing it as a top-level view parameter
    // causes View::gatherData() to eagerly execute $row->render(), converting $row to a string.
    $view = view()->make('blatui-admin::grid.table', [
        'rows' => collect([$row]),
        'columns' => collect([$col]),
    ]);

    // Top-level $data variables that are Renderable get stringified
    $dataWithRow = ['row' => $row];
    $gatherDataMethod = new ReflectionMethod($view, 'gatherData');

    // Simulate passing row directly in view data:
    $view->with('direct_row', $row);
    $gathered = $gatherDataMethod->invoke($view);

    if ($row instanceof Renderable) {
        // If Row is Renderable, $gathered['direct_row'] is stringified
        expect(is_string($gathered['direct_row']))->toBeTrue()
            ->and($gathered['direct_row'])->toBe($row->render());
    } else {
        expect($gathered['direct_row'])->toBeInstanceOf(Row::class);
    }
});
