<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function packageViewPath(string $relative = ''): string
{
    $base = dirname(__DIR__, 2).'/resources/views';

    return $relative ? $base.'/'.$relative : $base;
}

test('views do not reference cdn.jsdelivr.net or external CDNs', function () {
    $viewFiles = File::allFiles(packageViewPath());

    foreach ($viewFiles as $file) {
        $content = $file->getContents();
        expect($content)->not->toContain('cdn.jsdelivr.net')
            ->and($content)->not->toContain('cdn.min.js')
            ->and($content)->not->toContain('@tailwindcss/browser')
            ->and($content)->not->toContain('unpkg.com');
    }
});

test('views do not reference legacy theme localStorage key', function () {
    $viewFiles = File::allFiles(packageViewPath());

    foreach ($viewFiles as $file) {
        $content = $file->getContents();
        expect($content)->not->toContain("localStorage.getItem('theme')")
            ->and($content)->not->toContain("localStorage.setItem('theme'");
    }
});

test('layout does not use duplicate darkMode x-data state', function () {
    $layout = File::get(packageViewPath('layouts/app.blade.php'));
    expect($layout)->not->toContain(":class=\"{ 'dark': darkMode }\"")
        ->and($layout)->toContain('vendor/blatui-admin/admin.css')
        ->and($layout)->toContain('vendor/blatui-admin/admin.js');
});

test('login view renders compiled assets and does not use CDNs', function () {
    $login = File::get(packageViewPath('auth/login.blade.php'));
    expect($login)->toContain('vendor/blatui-admin/admin.css')
        ->and($login)->toContain('vendor/blatui-admin/admin.js');
});

test('header dark mode toggle delegates to themeStore', function () {
    $header = File::get(packageViewPath('partials/header.blade.php'));
    expect($header)->toContain('$store.theme.toggle()')
        ->and($header)->toContain('$store.theme.isDark');
});
