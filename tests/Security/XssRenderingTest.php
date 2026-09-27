<?php

declare(strict_types=1);

use BlatUI\Admin\Layout\Content;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;

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
