<?php

declare(strict_types=1);

use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Layout\Row;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable as SupportStringable;

/*
|--------------------------------------------------------------------------
| Milestone 2 (R2) Empirical Verification: Layout Content Rendering & Escaping
|--------------------------------------------------------------------------
|
| Tests cover:
| 1. Content::render() passes Illuminate\Support\HtmlString for 'content'
|    and resources/views/layouts/app.blade.php {{ $content }} renders
|    grid rows as actual HTML without escaping layout tags.
| 2. Raw unvetted malicious strings passed as $content to
|    view('blatui-admin::layouts.app', ['content' => ...]) are strictly
|    escaped by Blade {{ $content }} into HTML entities (&lt;script&gt;...).
| 3. XSS payload matrix across various attack vectors (img, svg, iframe, a href, script).
| 4. Type coercion and edge cases for $content:
|    - null, empty string, integer 0, boolean false
|    - Stringable object with malicious script (strictly escaped)
|    - Htmlable object (trusted HTML preserved)
| 5. End-to-end Content layout rendering with title/description XSS escaping
|    alongside preserved row HTML.
*/

dataset('layout_xss_payloads', [
    '<script>alert(1)</script>',
    '<script src="https://evil.com/xss.js"></script>',
    '"><img src=x onerror=alert(1)>',
    '<img src="javascript:alert(1)">',
    '\' onfocus=\'alert(1)',
    '<svg onload=alert(1)>',
    '<iframe src="javascript:alert(1)"></iframe>',
    '<a href="javascript:alert(1)">click me</a>',
    '"><script>alert(document.cookie)</script>',
    '<body onload=alert(1)>',
]);

test('2.1. Content::render() provides HtmlString for content key in view data', function () {
    $interceptedData = null;

    view()->composer('blatui-admin::layouts.app', function ($view) use (&$interceptedData) {
        $interceptedData = $view->getData();
    });

    $content = Content::make()
        ->title('Dashboard')
        ->row('<div id="test-row-1" class="p-4 bg-white">Sample Row 1</div>');

    $html = $content->render();

    expect($interceptedData)->toBeArray()
        ->and($interceptedData)->toHaveKey('content');

    $contentVar = $interceptedData['content'];

    // Verify it is specifically an instance of Illuminate\Support\HtmlString
    expect($contentVar)->toBeInstanceOf(HtmlString::class)
        ->and($contentVar)->toBeInstanceOf(Htmlable::class)
        ->and($contentVar->toHtml())->toContain('<div id="test-row-1" class="p-4 bg-white">Sample Row 1</div>');

    // Verify the rendered layout HTML contains actual unescaped layout tags
    expect($html)->toContain('<div id="test-row-1" class="p-4 bg-white">Sample Row 1</div>')
        ->not->toContain('&lt;div id=&quot;test-row-1&quot;')
        ->not->toContain('&amp;lt;div');
});

test('2.2. Content::render() with nested rows and columns escapes malicious row classes', function () {
    $content = Content::make()
        ->row(function (Row $row) {
            $row->class('grid"><script>alert(1)</script>');
            $row->column(6, 'Left Column Content');
        });

    $html = $content->render();

    // Must NOT contain the raw script tag
    expect($html)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<script>');

    // Must contain the entity-encoded script tag
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('2.3. passing raw unvetted malicious string as content to app layout is safely escaped', function (string $xss) {
    $html = view('blatui-admin::layouts.app', [
        'title' => 'Security Test',
        'content' => $xss,
    ])->render();

    // Extract the <main> section to inspect rendered content area specifically
    preg_match('/<main[^>]*>(.*?)<\/main>/s', $html, $matches);
    $mainContent = $matches[1] ?? $html;

    // Verbatim dangerous tags must NOT be present unescaped anywhere in the main content area
    expect($mainContent)->not->toContain('<script>')
        ->not->toContain('<script ')
        ->not->toContain('<img ')
        ->not->toContain('<svg')
        ->not->toContain('<iframe')
        ->not->toContain('<body onload=');

    // Blade {{ $content }} must have sanitized the payload to htmlspecialchars entities
    $expectedEscaped = e($xss);
    expect($html)->toContain($expectedEscaped)
        ->not->toContain($xss);
})->with('layout_xss_payloads');

test('2.4. explicit test case: passing <script>alert(1)</script> escapes to &lt;script&gt;alert(1)&lt;/script&gt;', function () {
    $payload = '<script>alert(1)</script>';

    $html = view('blatui-admin::layouts.app', [
        'content' => $payload,
    ])->render();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('2.5. layout content edge cases: null, empty string, zero integer, boolean false', function () {
    // 1. null content
    $htmlNull = view('blatui-admin::layouts.app', [
        'content' => null,
    ])->render();
    expect($htmlNull)->toBeString()
        ->and($htmlNull)->toContain('BlatUI Admin');

    // 2. empty string content
    $htmlEmpty = view('blatui-admin::layouts.app', [
        'content' => '',
    ])->render();
    expect($htmlEmpty)->toBeString()
        ->and($htmlEmpty)->toContain('BlatUI Admin');

    // 3. zero integer content
    $htmlZero = view('blatui-admin::layouts.app', [
        'content' => 0,
    ])->render();
    expect($htmlZero)->toContain('<main class="flex-1 p-4 sm:p-6 lg:p-8">')
        ->and($htmlZero)->toContain('0');

    // 4. zero string content '0'
    $htmlZeroStr = view('blatui-admin::layouts.app', [
        'content' => '0',
    ])->render();
    expect($htmlZeroStr)->toContain('0');

    // 5. boolean false content
    $htmlFalse = view('blatui-admin::layouts.app', [
        'content' => false,
    ])->render();
    expect($htmlFalse)->toBeString();
});

test('2.6. Stringable object containing XSS is escaped by Blade {{ $content }}', function () {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return '<script>alert("stringable-xss")</script>';
        }
    };

    $html = view('blatui-admin::layouts.app', [
        'content' => $stringable,
    ])->render();

    expect($html)->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(&quot;stringable-xss&quot;)&lt;/script&gt;');

    // Test Laravel Support Stringable as well
    $laravelStringable = new SupportStringable('"><img src=x onerror=alert(2)>');
    $html2 = view('blatui-admin::layouts.app', [
        'content' => $laravelStringable,
    ])->render();

    expect($html2)->not->toContain('<img')
        ->toContain('&quot;&gt;&lt;img src=x onerror=alert(2)&gt;');
});

test('2.7. Htmlable object is recognized as trusted HTML by Blade {{ $content }}', function () {
    $trusted = new HtmlString('<div id="trusted-widget" class="bg-blue-50">Trusted Custom Widget</div>');

    $html = view('blatui-admin::layouts.app', [
        'content' => $trusted,
    ])->render();

    expect($html)->toContain('<div id="trusted-widget" class="bg-blue-50">Trusted Custom Widget</div>')
        ->not->toContain('&lt;div id=&quot;trusted-widget&quot;');
});

test('2.8. End-to-end Content layout escapes title and description while rendering rows HTML', function () {
    $content = Content::make()
        ->title('<script>alert("title-xss")</script>')
        ->description('<img src=x onerror=alert("desc-xss")>')
        ->row('<section class="dashboard-stats"><p>Active Users: 1,234</p></section>');

    $html = $content->render();

    // Title and description must be escaped
    expect($html)->not->toContain('<script>alert("title-xss")</script>')
        ->not->toContain('<img src=x onerror=alert("desc-xss")>')
        ->toContain('&lt;script&gt;alert(&quot;title-xss&quot;)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(&quot;desc-xss&quot;)&gt;');

    // Content rows must be unescaped HTML
    expect($html)->toContain('<section class="dashboard-stats"><p>Active Users: 1,234</p></section>')
        ->not->toContain('&lt;section');
});

test('2.9. with() cannot hijack content key with raw unvetted script string', function () {
    $content = Content::make()
        ->with('content', '<script>alert("hijacked")</script>')
        ->row('<p>Legitimate Row</p>');

    $html = $content->render();

    // The injected malicious string in with() must be overwritten by renderRows() HtmlString
    expect($html)->not->toContain('<script>alert("hijacked")</script>')
        ->toContain('<p>Legitimate Row</p>');
});

test('2.10. View::gatherData() eagerly stringifies Renderable objects, causing Blade to escape them, proving why Content::render() must provide HtmlString', function () {
    // When an object implements Renderable (like Content or a custom View/Component),
    // Laravel's View::gatherData() converts it to a raw PHP string during view preparation.
    // As a result, Blade {{ $content }} treats it as an unvetted string and htmlspecialchars-escapes it.
    $renderable = new class implements Renderable
    {
        public function render(): string
        {
            return '<div class="renderable">Renderable Content</div>';
        }
    };

    $html = view('blatui-admin::layouts.app', [
        'content' => $renderable,
    ])->render();

    // Verify it was escaped because View::gatherData() stringified it into a plain string:
    expect($html)->not->toContain('<div class="renderable">Renderable Content</div>')
        ->toContain('&lt;div class=&quot;renderable&quot;&gt;Renderable Content&lt;/div&gt;');

    // In contrast, HtmlString implements ONLY Htmlable (not Renderable), so View::gatherData()
    // preserves the HtmlString instance, allowing {{ $content }} to output verbatim HTML:
    $htmlString = new HtmlString('<div class="html-string">Trusted Content</div>');
    $html2 = view('blatui-admin::layouts.app', [
        'content' => $htmlString,
    ])->render();

    expect($html2)->toContain('<div class="html-string">Trusted Content</div>')
        ->not->toContain('&lt;div class=&quot;html-string&quot;&gt;');
});

test('2.11. layout row HTML preserves entities and attributes without double-escaping', function () {
    $rowHtml = '<div class="alert" data-options="&quot;key&quot;: &quot;value&quot;">'
        .'<span>Terms &amp; Conditions</span>'
        .'<button type="button" class="btn">Click &amp; Save</button>'
        .'</div>';

    $content = Content::make()->row($rowHtml);
    $html = $content->render();

    expect($html)->toContain($rowHtml)
        ->not->toContain('&amp;amp;');
});
