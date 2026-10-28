<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('compiles all documentation Blade examples cleanly into previews and manifest', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $examplesDir = $repoRoot.'/pages/docs/examples';
    $manifestPath = $repoRoot.'/pages/docs/.vitepress/previews.json';
    $previewsDir = $repoRoot.'/pages/docs/public/previews';

    $exampleFiles = glob($examplesDir.'/*.blade.php') ?: [];
    expect($exampleFiles)->not->toBeEmpty();

    $process = new Process([PHP_BINARY, $repoRoot.'/scripts/compile-docs-examples.php'], $repoRoot);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    expect(file_exists($manifestPath))->toBeTrue();

    /** @var array<string, array{name: string, code: string, html: string, previewUrl: string}> $manifest */
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    foreach ($exampleFiles as $filePath) {
        $name = substr(basename($filePath), 0, -strlen('.blade.php'));

        expect($manifest)->toHaveKey($name);
        expect($manifest[$name]['code'])->not->toBeEmpty();
        expect($manifest[$name]['html'])->not->toBeEmpty();
        expect($manifest[$name]['html'])->not->toContain('<x-ui.');
        expect($manifest[$name]['html'])->not->toContain('<x-block.');
        expect($manifest[$name]['html'])->not->toContain('<x-lucide-');
        expect(file_exists($previewsDir.'/'.$name.'.html'))->toBeTrue();
    }
});

it('ensures all BladePreview components in markdown docs reference existing examples', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $docsDir = $repoRoot.'/pages/docs';
    $examplesDir = $docsDir.'/examples';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docsDir, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'md') {
            continue;
        }

        $path = $file->getRealPath();

        if ($path === false || str_contains($path, '/node_modules/') || str_contains($path, '/.vitepress/')) {
            continue;
        }

        $markdown = (string) file_get_contents($path);
        // Strip fenced code blocks before scanning for live <BladePreview> tags
        $withoutCodeBlocks = (string) preg_replace('/```[\s\S]*?```/', '', $markdown);

        if (preg_match_all('/<BladePreview[^>]*\bname=["\']([^"\']+)["\']/', $withoutCodeBlocks, $matches) > 0) {
            foreach ($matches[1] as $exampleName) {
                expect(file_exists($examplesDir.'/'.$exampleName.'.blade.php'))
                    ->toBeTrue("Missing pages/docs/examples/{$exampleName}.blade.php referenced in {$file->getFilename()}");
            }
        }
    }
});

it('supports @error directives and cleans up stale HTML previews when examples are removed', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $tempDir = sys_get_temp_dir().'/blatui-docs-valid-'.uniqid();
    $examplesDir = $tempDir.'/examples';
    $previewsDir = $tempDir.'/previews';
    $manifestPath = $tempDir.'/previews.json';

    mkdir($examplesDir, 0755, true);
    mkdir($previewsDir, 0755, true);

    file_put_contents($previewsDir.'/stale-example.html', '<html>stale</html>');
    file_put_contents($previewsDir.'/preview.css', '/* keep css */');
    file_put_contents(
        $examplesDir.'/form-demo.blade.php',
        '<x-ui.input type="email" placeholder="admin@example.com" /> @error("email") <span>Invalid</span> @enderror',
    );

    try {
        $process = new Process([
            PHP_BINARY,
            $repoRoot.'/scripts/compile-docs-examples.php',
            $examplesDir,
            $previewsDir,
            $manifestPath,
        ], $repoRoot);
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        expect(file_exists($previewsDir.'/stale-example.html'))->toBeFalse();
        expect(file_exists($previewsDir.'/preview.css'))->toBeTrue();
        expect(file_exists($previewsDir.'/form-demo.html'))->toBeTrue();
    } finally {
        deleteDirectory($tempDir);
    }
});

it('fails with non-zero exit code when a Blade example contains an invalid component', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $tempDir = sys_get_temp_dir().'/blatui-docs-test-'.uniqid();
    $examplesDir = $tempDir.'/examples';
    $previewsDir = $tempDir.'/previews';
    $manifestPath = $tempDir.'/previews.json';

    mkdir($examplesDir, 0755, true);
    file_put_contents($examplesDir.'/broken.blade.php', '<x-ui.non-existent-component />');

    try {
        $process = new Process([
            PHP_BINARY,
            $repoRoot.'/scripts/compile-docs-examples.php',
            $examplesDir,
            $previewsDir,
            $manifestPath,
        ], $repoRoot);
        $process->run();

        expect($process->isSuccessful())->toBeFalse();
        expect($process->getErrorOutput())->toContain('Failed to render Blade example [broken.blade.php]');
    } finally {
        deleteDirectory($tempDir);
    }
});
