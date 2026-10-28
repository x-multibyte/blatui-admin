<?php

declare(strict_types=1);

use BladeUI\Icons\BladeIconsServiceProvider;
use BlatUI\Admin\AdminServiceProvider;
use BlatUI\BlatuiServiceProvider;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Compilers\BladeCompiler;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use TailwindMerge\Laravel\TailwindMergeServiceProvider;

require_once __DIR__.'/../vendor/autoload.php';

$repoRoot = dirname(__DIR__);
$examplesDir = $argv[1] ?? $repoRoot.'/pages/docs/examples';
$previewsOutDir = $argv[2] ?? $repoRoot.'/pages/docs/public/previews';
$manifestPath = $argv[3] ?? $repoRoot.'/pages/docs/.vitepress/previews.json';

if (! is_dir($examplesDir)) {
    mkdir($examplesDir, 0755, true);
}

if (! is_dir($previewsOutDir)) {
    mkdir($previewsOutDir, 0755, true);
}

if (! is_dir(dirname($manifestPath))) {
    mkdir(dirname($manifestPath), 0755, true);
}

$defaultCacheDir = $repoRoot.'/vendor/orchestra/testbench-core/laravel/bootstrap/cache';
$defaultViewsDir = $repoRoot.'/vendor/orchestra/testbench-core/laravel/storage/framework/views';

if (! is_writable($defaultCacheDir) || ! is_writable($defaultViewsDir)) {
    $tempCacheDir = sys_get_temp_dir().'/blatui-docs-testbench-cache-'.md5($repoRoot);

    if (! is_dir($tempCacheDir)) {
        mkdir($tempCacheDir, 0755, true);
    }

    $cacheVars = [
        'APP_SERVICES_CACHE' => $tempCacheDir.'/services.php',
        'APP_PACKAGES_CACHE' => $tempCacheDir.'/packages.php',
        'APP_CONFIG_CACHE' => $tempCacheDir.'/config.php',
        'APP_ROUTES_CACHE' => $tempCacheDir.'/routes.php',
        'APP_EVENTS_CACHE' => $tempCacheDir.'/events.php',
        'VIEW_COMPILED_PATH' => $tempCacheDir,
    ];

    foreach ($cacheVars as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$app = Application::create(options: [
    'extra' => [
        'providers' => [
            TailwindMergeServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeLucideIconsServiceProvider::class,
            BlatuiServiceProvider::class,
            AdminServiceProvider::class,
        ],
    ],
]);

restore_exception_handler();
restore_error_handler();

/** @var BladeCompiler $blade */
$blade = $app->make('blade.compiler');

if (is_dir($repoRoot.'/resources/views/components')) {
    $blade->anonymousComponentPath($repoRoot.'/resources/views/components');
}

$blade->anonymousComponentPath($repoRoot.'/vendor/anousss007/blatui/stubs');

/** @var ViewFactory $view */
$view = $app->make('view');
$view->addLocation($examplesDir);
$view->share('errors', new ViewErrorBag);

/** @var array<int, string> $existingHtmlFiles */
$existingHtmlFiles = glob($previewsOutDir.'/*.html') ?: [];
foreach ($existingHtmlFiles as $staleHtmlFile) {
    @unlink($staleHtmlFile);
}

/** @var array<int, string> $exampleFiles */
$exampleFiles = glob($examplesDir.'/*.blade.php') ?: [];
sort($exampleFiles);

$manifest = [];

foreach ($exampleFiles as $filePath) {
    $filename = basename($filePath);
    $name = substr($filename, 0, -strlen('.blade.php'));
    $source = (string) file_get_contents($filePath);

    try {
        $renderedHtml = trim($blade->render($source));
    } catch (Throwable $e) {
        fwrite(STDERR, sprintf("Failed to render Blade example [%s]: %s\n", $filename, $e->getMessage()));

        throw $e;
    }

    $nameJson = json_encode($name, JSON_THROW_ON_ERROR);

    $standaloneDocument = <<<HTML
<!DOCTYPE html>
<html lang="en" data-radius="0.625">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BlatUI Preview - {$name}</title>
    <link rel="stylesheet" href="./preview.css">
    <script>
        (function () {
            function syncTheme(dark) {
                document.documentElement.classList.toggle('dark', Boolean(dark));
            }
            try {
                if (window.parent && window.parent !== window && window.parent.document) {
                    syncTheme(window.parent.document.documentElement.classList.contains('dark'));
                }
            } catch (e) {}
            window.addEventListener('message', function (event) {
                if (event.data && event.data.type === 'blatui-theme') {
                    syncTheme(event.data.dark);
                }
            });
            window.addEventListener('DOMContentLoaded', function () {
                var lastHeight = 0;
                var rafId = 0;
                var computeHeight = function () {
                    rafId = 0;
                    if (!document.body) return;
                    var height = document.body.offsetHeight;
                    var floating = document.querySelectorAll('[role="dialog"], [role="menu"], [role="listbox"], [data-slot$="-content"]');
                    for (var i = 0; i < floating.length; i++) {
                        var el = floating[i];
                        var style = window.getComputedStyle(el);
                        if (style.display !== 'none' && style.visibility !== 'hidden') {
                            var rect = el.getBoundingClientRect();
                            if (rect.height > 0 && rect.bottom > 0) {
                                height = Math.max(height, Math.ceil(rect.bottom + window.scrollY + 16));
                            }
                        }
                    }
                    height = Math.max(height, 120);
                    if (height !== lastHeight) {
                        lastHeight = height;
                        window.parent.postMessage({
                            type: 'blatui-preview-resize',
                            name: {$nameJson},
                            height: height
                        }, '*');
                    }
                };
                var scheduleNotify = function () {
                    if (!rafId) {
                        rafId = window.requestAnimationFrame(computeHeight);
                    }
                };
                computeHeight();
                if (typeof ResizeObserver !== 'undefined' && document.body) {
                    new ResizeObserver(scheduleNotify).observe(document.body);
                }
                if (typeof MutationObserver !== 'undefined' && document.body) {
                    new MutationObserver(scheduleNotify).observe(document.body, {
                        attributes: true,
                        childList: true,
                        subtree: true
                    });
                }
                window.addEventListener('load', computeHeight);
            });
        })();
    </script>
    <script type="module" src="./preview.js"></script>
</head>
<body class="bg-background text-foreground antialiased p-6">
    <div id="blatui-preview-root" class="mx-auto flex min-h-[88px] w-full items-center justify-center">
        {$renderedHtml}
    </div>
</body>
</html>
HTML;

    file_put_contents($previewsOutDir.'/'.$name.'.html', $standaloneDocument."\n");

    $manifest[$name] = [
        'name' => $name,
        'code' => trim($source),
        'html' => $renderedHtml,
        'previewUrl' => 'previews/'.$name.'.html',
    ];
}

file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
);

fwrite(STDOUT, sprintf("Compiled %d Blade documentation example(s).\n", count($manifest)));
