<?php

declare(strict_types=1);

use BlatUI\Admin\Grid\Filter\Between;
use BlatUI\Admin\Grid\Filter\Equal;
use BlatUI\Admin\Grid\Filter\In;
use BlatUI\Admin\Grid\Filter\Like;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Blade;

/**
 * Characterisation tests for Grid filter field rendering.
 *
 * These pin the exact markup each Field subclass emits so that a later
 * migration of that markup out of PHP and into Blade can be proven
 * behaviour-preserving. They assert against the CURRENT implementation
 * output: if a migration changes a class name, an attribute name, or the
 * GET form contract, these fail.
 *
 * The `between` and `in` rendering paths had no coverage at all before this
 * file — only their query-building behaviour was tested.
 */
test('equal and like fields render the shared text input markup', function (string $class) {
    $field = new $class('username', 'Username');

    $html = $field->render();

    expect($html)
        ->toContain('<input')
        ->toContain('type="text"')
        ->toContain('name="username"')
        ->toContain('id="'.$field->getId().'"')
        ->toContain('placeholder="Username"')
        ->toContain('Username')
        ->toContain('class="flex flex-col gap-1.5"');
})->with([
    'equal' => [Equal::class],
    'like' => [Like::class],
]);

test('text field renders its current value and escapes it', function () {
    $field = new Equal('username', 'Username');
    $field->value('admin');

    expect($field->render())
        ->toContain('value="admin"');
});

test('text field escapes an XSS payload in the value attribute', function () {
    $field = new Equal('username', 'Username');
    $field->value('"><script>alert(1)</script>');

    $html = $field->render();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

test('text field falls back to an empty value for non-scalar input', function () {
    $field = new Equal('username', 'Username');
    $field->value(['an', 'array']);

    $html = $field->render();

    expect($html)
        ->toContain('value=""')
        ->not->toContain('value="Array"');
});

test('between field renders two bounds with distinct name attributes', function () {
    $field = new Between('created_at', 'Created At');

    $html = $field->render();

    // The GET form contract: nested array notation, not suffixed names.
    expect($html)
        ->toContain('name="created_at[start]"')
        ->toContain('name="created_at[end]"')
        ->toContain('id="'.$field->getId().'_start"')
        ->toContain('id="'.$field->getId().'_end"')
        ->toContain('placeholder="From"')
        ->toContain('placeholder="To"')
        ->toContain('Created At');
});

test('between field prefills both bounds from the request', function () {
    $field = new Between('created_at', 'Created At');
    $field->value(['start' => '2026-01-01', 'end' => '2026-12-31']);

    $html = $field->render();

    expect($html)
        ->toContain('value="2026-01-01"')
        ->toContain('value="2026-12-31"');
});

test('between field renders both inputs with the range input class', function () {
    $html = (new Between('created_at', 'Created At'))->render();

    expect($html)
        ->toContain('px-2.5 py-1')   // the narrower range control
        ->toContain('<span class="text-xs text-gray-400 shrink-0">—</span>');
});

test('in field renders a select with an All option and the configured choices', function () {
    $field = new In('id', 'IDs');
    $field->options([1 => 'Admin', 2 => 'Editor']);

    $html = $field->render();

    expect($html)
        ->toContain('<select')
        ->toContain('name="id"')
        ->toContain('<option value="">All</option>')
        ->toContain('<option value="1">Admin</option>')
        ->toContain('<option value="2">Editor</option>');
});

test('in field marks options matching the current selection', function () {
    $field = new In('id', 'IDs');
    $field->options([1 => 'Admin', 2 => 'Editor']);
    $field->value('2');

    $html = $field->render();

    expect($html)
        ->toContain('<option value="2" selected>Editor</option>')
        ->toContain('<option value="1">Admin</option>');
});

test('in field escapes option labels and keys', function () {
    $field = new In('id', 'IDs');
    $field->options(['"><script>alert(1)</script>' => '<img src=x onerror=alert(1)>']);

    $html = $field->render();

    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<img src=x onerror=alert(1)>');
});

test('in field falls back to the shared text input when no options are set', function () {
    $field = new In('username', 'Username');

    $html = $field->render();

    expect($html)
        ->not->toContain('<select')
        ->toContain('type="text"')
        ->toContain('name="username"');
});

/**
 * Structural fingerprint of the emitted markup.
 *
 * Reduces each rendered field to its DOM skeleton — element names, in
 * document order, with every whitespace-normalised value replaced by a
 * placeholder. This catches any accidental change to element nesting, tag
 * order, or attribute presence during a markup migration, while deliberately
 * ignoring the dynamic values (ids, values, labels) that legitimately vary.
 *
 * Compare these fingerprints before and after moving markup from PHP to
 * Blade. They must be identical.
 */
function fingerprint(string $html): array
{
    $html = preg_replace('/>\s+</', '><', trim($html));

    preg_match_all('/<(\w+)([^>]*)>/', $html, $matches, PREG_SET_ORDER);

    return array_map(function (array $m): string {
        $tag = $m[1];
        preg_match_all('/(\w[-\w]*)="[^"]*"/', $m[2], $attrs, PREG_SET_ORDER);
        $names = array_map(fn (array $a): string => $a[1], $attrs);
        sort($names);

        return $tag.'['.implode(',', $names).']';
    }, $matches);
}

test('field markup fingerprints are stable', function (string $class, array $config, array $options, string $expected) {
    $field = new $class(...$config);

    if ($options !== [] && method_exists($field, 'options')) {
        $field->options($options);
    }

    expect(fingerprint($field->render()))
        ->toBe(explode(' ', $expected));
})->with([
    'equal' => [
        Equal::class,
        ['username', 'Username'],
        [],
        'div[class] label[class,for] div[class] input[class,id,name,placeholder,type,value]',
    ],
    'between' => [
        Between::class,
        ['created_at', 'Created At'],
        [],
        'div[class] label[class] div[class] input[class,id,name,placeholder,type,value]'.
            ' span[class] input[class,id,name,placeholder,type,value]',
    ],
    'in-with-options' => [
        In::class,
        ['id', 'IDs'],
        [1 => 'Admin', 2 => 'Editor'],
        'div[class] label[class,for] div[class] select[class,id,name]'.
            ' option[value] option[value] option[value]',
    ],
    'in-without-options-falls-back-to-text' => [
        In::class,
        ['username', 'Username'],
        [],
        'div[class] label[class,for] div[class] input[class,id,name,placeholder,type,value]',
    ],
]);

test('in field renders a comma separated selection', function () {
    $field = new In('id', 'IDs');
    $field->options([1 => 'Admin', 2 => 'Editor', 3 => 'Viewer']);
    $field->value('1,3');

    $html = $field->render();

    expect($html)
        ->toContain('<option value="1" selected>Admin</option>')
        ->toContain('<option value="3" selected>Viewer</option>')
        ->toContain('<option value="2">Editor</option>');
});

test('filter fields use configured default blade views', function () {
    $equal = new Equal('title', 'Title');
    $between = new Between('created_at', 'Created At');
    $in = new In('status', 'Status');

    expect($equal->getView())->toBe('blatui-admin::grid.filter.text')
        ->and($between->getView())->toBe('blatui-admin::grid.filter.between')
        ->and($in->getView())->toBe('blatui-admin::grid.filter.select')
        ->and($in->getFallbackView())->toBe('blatui-admin::grid.filter.text');
});

test('filter fields allow customizing the blade view and fallback view', function () {
    $field = new Equal('title', 'Title');
    $field->view('custom.filter.text');
    expect($field->getView())->toBe('custom.filter.text');

    $in = new In('status', 'Status');
    $in->view('custom.filter.select')->fallbackView('custom.filter.fallback');
    expect($in->getView())->toBe('custom.filter.select')
        ->and($in->getFallbackView())->toBe('custom.filter.fallback');
});

test('field proxy inside blade prevents recursive evaluation loop', function () {
    $field = new Equal('title', 'Title');

    // A blade snippet attempting to cast $field directly to string
    expect(fn () => Blade::render('{{ $field }}', [
        'field' => (new ReflectionMethod($field, 'newProxy'))->invoke($field),
    ]))->toThrow(Exception::class);
});

test('field implements Htmlable, Renderable, and Stringable returning identical output', function () {
    $field = new Equal('title', 'Title');

    expect($field)->toBeInstanceOf(Htmlable::class)
        ->and($field)->toBeInstanceOf(Renderable::class)
        ->and($field)->toBeInstanceOf(Stringable::class)
        ->and($field->toHtml())->toBe($field->render())
        ->and((string) $field)->toBe($field->render());
});

test('filter fields source code contains zero heredocs', function () {
    $files = [
        __DIR__.'/../../src/Grid/Filter/Field.php',
        __DIR__.'/../../src/Grid/Filter/Between.php',
        __DIR__.'/../../src/Grid/Filter/In.php',
    ];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain('<<<HTML')
            ->not->toContain('<<<');
    }
});
