<?php

declare(strict_types=1);

use BlatUI\Admin\Layout\Content;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\HtmlString;

test('Content implements Htmlable and Renderable contracts', function () {
    $content = new Content;

    expect($content)->toBeInstanceOf(Htmlable::class)
        ->and($content)->toBeInstanceOf(Renderable::class)
        ->and($content->toHtml())->toBeString();
});

test('Content layout renderFallback escapes title and description', function () {
    $content = new Content;
    $content->title('<script>alert("title")</script>');
    $content->description('<script>alert("desc")</script>');

    // Force renderFallback by setting a view that does not exist
    $content->view('blatui-admin::non-existent-custom-view');
    $html = $content->render();

    expect($html)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;')
        ->toContain('&lt;script&gt;alert(&quot;desc&quot;)&lt;/script&gt;');
});

test('LayoutComposer intercepts layout views and sanitizes injected strings', function () {
    $view = view('blatui-admin::layouts.fallback', [
        'title' => '<script>alert("layout-title")</script>',
        'description' => '<img src=x onerror=alert(1)>',
        'rows' => [],
    ]);

    $html = $view->render();

    expect($html)
        ->not->toContain('<script>')
        ->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;script&gt;alert(&quot;layout-title&quot;)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

test('LayoutComposer preserves pre-escaped Htmlable instances without double-escaping', function () {
    $safeBadge = new HtmlString('<span class="badge">Safe Badge</span>');
    $view = view('blatui-admin::layouts.fallback', [
        'title' => $safeBadge,
        'description' => 'Plain & Simple',
        'rows' => [],
    ]);

    $html = $view->render();

    expect($html)
        ->toContain('<span class="badge">Safe Badge</span>')
        ->not->toContain('&lt;span class=')
        ->toContain('Plain &amp; Simple');
});

test('GridComposer intercepts grid views and sanitizes injected scalar strings', function () {
    $view = view('blatui-admin::grid.pagination', [
        'paginator' => null,
        'injected_title' => '<script>alert("grid-xss")</script>',
    ]);

    // Force rendering / composing
    $view->render();

    $viewData = $view->getData();
    expect($viewData['injected_title'])->toBeInstanceOf(Htmlable::class)
        ->and((string) $viewData['injected_title'])->toBe('&lt;script&gt;alert(&quot;grid-xss&quot;)&lt;/script&gt;');
});
