<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function packageViewPath(string $relative = ''): string
{
    $base = dirname(__DIR__, 2).'/resources/views';

    return $relative ? $base.'/'.$relative : $base;
}

test('views do not reference cdn.jsdelivr.net', function () {
    $viewFiles = File::allFiles(packageViewPath());

    foreach ($viewFiles as $file) {
        $content = $file->getContents();
        expect($content)->not->toContain('cdn.jsdelivr.net')
            ->and($content)->not->toContain('@tailwindcss/browser');
    }
});

test('layout does not use duplicate darkMode x-data state', function () {
    $layout = File::get(packageViewPath('layouts/app.blade.php'));
    expect($layout)->not->toContain("localStorage.getItem('theme')")
        ->and($layout)->not->toContain(":class=\"{ 'dark': darkMode }\"")
        ->and($layout)->toContain('vendor/blatui-admin/admin.css')
        ->and($layout)->toContain('vendor/blatui-admin/admin.js');
});

test('header dark mode toggle delegates to themeStore', function () {
    $header = File::get(packageViewPath('partials/header.blade.php'));
    expect($header)->toContain('$store.theme.toggle()')
        ->and($header)->toContain('$store.theme.isDark');
});
