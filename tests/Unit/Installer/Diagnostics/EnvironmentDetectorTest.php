<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Diagnostics\CheckStatus;
use BlatUI\Admin\Console\Installer\Diagnostics\DiagnosticReport;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use Illuminate\Support\Facades\Process;

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

it('isolates composer package detection to target basePath without host runner leak', function () {
    $detector = new EnvironmentDetector($this->tempDir);

    // Host app has pestphp/pest installed in runner vendor, but target tempDir has no composer files
    expect($detector->hasComposerPackage('pestphp/pest'))->toBeFalse()
        ->and($detector->hasComposerPackage('anousss007/blatui'))->toBeFalse();

    // Now write to target composer.json
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['anousss007/blatui' => '^1.36'],
        'require-dev' => ['pestphp/pest' => '^4.0'],
    ]));

    expect($detector->hasComposerPackage('anousss007/blatui'))->toBeTrue()
        ->and($detector->hasComposerPackage('pestphp/pest'))->toBeTrue()
        ->and($detector->hasComposerPackage('nonexistent/package'))->toBeFalse();
});

it('detects composer packages from target composer.lock', function () {
    file_put_contents($this->tempDir.'/composer.lock', json_encode([
        'packages' => [
            ['name' => 'livewire/livewire', 'version' => '3.5.0'],
        ],
        'packages-dev' => [
            ['name' => 'laravel/pint', 'version' => '1.20.0'],
        ],
    ]));

    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->hasComposerPackage('livewire/livewire'))->toBeTrue()
        ->and($detector->hasComposerPackage('laravel/pint'))->toBeTrue()
        ->and($detector->hasComposerPackage('nonexistent/package'))->toBeFalse();
});

it('checks composer binary presence and failure', function () {
    Process::fake([
        '*composer*' => Process::result('Composer version 2.7.0', 0),
    ]);

    $detector = new EnvironmentDetector($this->tempDir);
    expect($detector->checkComposer()->status)->toBe(CheckStatus::PASS);

    Process::fake([
        '*composer*' => Process::result(output: '', exitCode: 1),
    ]);

    expect($detector->checkComposer()->status)->toBe(CheckStatus::FAIL)
        ->and($detector->checkComposer()->suggestedFix)->toContain('getcomposer.org');
});

it('checks node version including handling banner warnings', function () {
    Process::fake([
        '*node*' => Process::result("(node:4512) ExperimentalWarning: CommonJS\nv20.18.0\n", 0),
    ]);

    $detector = new EnvironmentDetector($this->tempDir);
    $result = $detector->checkNode();

    expect($detector->nodeMajorVersion())->toBe(20)
        ->and($result->status)->toBe(CheckStatus::PASS)
        ->and($result->message)->toContain('v20');

    Process::fake([
        '*node*' => Process::result('v16.14.0', 0),
    ]);

    $resultOld = $detector->checkNode();
    expect($detector->nodeMajorVersion())->toBe(16)
        ->and($resultOld->status)->toBe(CheckStatus::FAIL)
        ->and($resultOld->message)->toContain('Node.js 18+ is required');

    Process::fake([
        '*node*' => Process::result('', 127),
    ]);

    $resultMissing = $detector->checkNode();
    expect($detector->nodeMajorVersion())->toBeNull()
        ->and($resultMissing->status)->toBe(CheckStatus::FAIL);
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

it('detects package manager from package.json packageManager field both versioned and unversioned', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'packageManager' => 'yarn@4.1.0',
    ]));

    $detector = new EnvironmentDetector($this->tempDir);
    expect($detector->detectNodePackageManager())->toBe('yarn');

    file_put_contents($this->tempDir.'/package.json', json_encode([
        'packageManager' => 'pnpm',
    ]));

    expect($detector->detectNodePackageManager())->toBe('pnpm');
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

it('checks livewire presence and adaptation', function () {
    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->hasLivewire())->toBeFalse()
        ->and($detector->checkLivewire()->message)->toContain('standalone Alpine');

    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['livewire/livewire' => '^3.5'],
    ]));

    expect($detector->hasLivewire())->toBeTrue()
        ->and($detector->checkLivewire()->message)->toContain('alpine:init');
});

it('checks flux ui presence and dark mode theme parameter hint', function () {
    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->hasFlux())->toBeFalse()
        ->and($detector->checkFlux()->message)->toBe('No conflict detected');

    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['livewire/flux' => '^2.0'],
    ]));

    expect($detector->hasFlux())->toBeTrue()
        ->and($detector->checkFlux()->message)->toContain('{ theme: false }');
});

it('checks alpine dependency conflict against duplicate alpine', function () {
    $detector = new EnvironmentDetector($this->tempDir);

    // Greenfield without Livewire
    expect($detector->checkAlpine()->status)->toBe(CheckStatus::PASS);

    // Livewire + npm alpinejs -> duplicate Alpine warning
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['livewire/livewire' => '^3.0'],
    ]));
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'dependencies' => ['alpinejs' => '^3.14.0'],
    ]));

    $livewireDetector = new EnvironmentDetector($this->tempDir);

    expect($livewireDetector->checkAlpine()->status)->toBe(CheckStatus::WARN)
        ->and($livewireDetector->checkAlpine()->message)->toContain('Both Livewire and npm alpinejs');
});

it('checks blatui core and peer package detection', function () {
    $detector = new EnvironmentDetector($this->tempDir);

    expect($detector->checkBlatUI()->status)->toBe(CheckStatus::WARN)
        ->and($detector->checkPeerPackages()->status)->toBe(CheckStatus::WARN)
        ->and($detector->checkPeerPackages()->message)->toContain('tailwind-merge-laravel');

    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => [
            'anousss007/blatui' => '^1.36',
            'gehrisandro/tailwind-merge-laravel' => '^1.4',
            'mallardduck/blade-lucide-icons' => '^2.0',
        ],
    ]));

    expect($detector->checkBlatUI()->status)->toBe(CheckStatus::PASS)
        ->and($detector->checkPeerPackages()->status)->toBe(CheckStatus::PASS);
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
        ->and($report->count())->toBe(10)
        ->and($report->results())->toHaveCount(10);
});
