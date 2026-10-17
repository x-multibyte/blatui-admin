<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Diagnostics\CheckStatus;
use BlatUI\Admin\Console\Installer\Diagnostics\DiagnosticReport;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/blatui_test_'.uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($this->tempDir);
    }
});

it('detects PHP version correctly', function () {
    $detector = new EnvironmentDetector($this->tempDir);
    $result = $detector->checkPhp();

    expect($result->name)->toBe('PHP Version')
        ->and($result->status)->toBe(CheckStatus::PASS)
        ->and($result->message)->toContain(PHP_VERSION);
});

it('detects package manager from lockfiles', function () {
    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->detectNodePackageManager())->toBeNull();

    touch($this->tempDir.'/pnpm-lock.yaml');
    expect($detector->detectNodePackageManager())->toBe('pnpm')
        ->and($detector->checkPackageManager()->status)->toBe(CheckStatus::PASS)
        ->and($detector->checkPackageManager()->message)->toContain('pnpm');
});

it('detects package manager from bun lockfile', function () {
    touch($this->tempDir.'/bun.lock');
    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->detectNodePackageManager())->toBe('bun');
});

it('detects package manager from package.json packageManager field', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'packageManager' => 'yarn@4.1.0',
    ]));

    $detector = new EnvironmentDetector($this->tempDir);
    expect($detector->detectNodePackageManager())->toBe('yarn');
});

it('detects tailwind css version and warns on v3', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'devDependencies' => [
            'tailwindcss' => '^3.4.1',
        ],
    ]));

    $detector = new EnvironmentDetector($this->tempDir);
    $result = $detector->checkTailwind();

    expect($detector->tailwindMajorVersion())->toBe(3)
        ->and($result->status)->toBe(CheckStatus::WARN)
        ->and($result->suggestedFix)->toContain('@tailwindcss/upgrade');
});

it('detects tailwind css v4 as pass', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'devDependencies' => [
            'tailwindcss' => '^4.0.0',
        ],
    ]));

    $detector = new EnvironmentDetector($this->tempDir);
    $result = $detector->checkTailwind();

    expect($detector->tailwindMajorVersion())->toBe(4)
        ->and($result->status)->toBe(CheckStatus::PASS);
});

it('detects node dependencies from package.json', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'dependencies' => ['apexcharts' => '^3.45.0'],
        'devDependencies' => ['vite' => '^6.0.0'],
    ]));

    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->hasNodeDependency('apexcharts'))->toBeTrue()
        ->and($detector->hasNodeDependency('vite'))->toBeTrue()
        ->and($detector->hasNodeDependency('nonexistent'))->toBeFalse();
});

it('runs full diagnostics and returns aggregated report', function () {
    $detector = new EnvironmentDetector($this->tempDir);
    $report = $detector->fullDiagnostics();

    expect($report)->toBeInstanceOf(DiagnosticReport::class)
        ->and($report->count())->toBeGreaterThan(5);
});
