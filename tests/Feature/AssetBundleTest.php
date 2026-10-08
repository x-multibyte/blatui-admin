<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('precompiled assets exist and are non-empty in dist directory', function () {
    $cssPath = dirname(__DIR__, 2).'/dist/admin.css';
    $jsPath = dirname(__DIR__, 2).'/dist/admin.js';

    expect(File::exists($cssPath))->toBeTrue()
        ->and(File::size($cssPath))->toBeGreaterThan(1024)
        ->and(File::exists($jsPath))->toBeTrue()
        ->and(File::size($jsPath))->toBeGreaterThan(1024);
});
