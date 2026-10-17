<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Diagnostics;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Process;
use Throwable;

final class EnvironmentDetector
{
    private string $basePath;

    private string $defaultBasePath;

    /**
     * @var array<string, string>|null
     */
    private ?array $nodeDependenciesCache = null;

    public function __construct(?string $basePath = null)
    {
        $defaultBase = function_exists('base_path') ? base_path() : (getcwd() ?: '.');
        $this->defaultBasePath = rtrim($defaultBase, '/');
        $this->basePath = rtrim($basePath ?? $defaultBase, '/');
    }

    public function basePath(string $path = ''): string
    {
        return $path === '' ? $this->basePath : $this->basePath.'/'.ltrim($path, '/');
    }

    public function checkPhp(): CheckResult
    {
        $version = PHP_VERSION;
        $id = filter_var((string) PHP_VERSION_ID, FILTER_VALIDATE_INT);

        if ($id === false || $id < 80300) {
            return CheckResult::fail(
                'PHP Version',
                "PHP 8.3 or newer is required ({$version} detected).",
                'Upgrade your PHP runtime to 8.3 or higher.',
            );
        }

        return CheckResult::pass('PHP Version', "v{$version} detected");
    }

    public function checkComposer(): CheckResult
    {
        if (! $this->hasBinary('composer') && ! $this->hasBinary('composer.phar')) {
            return CheckResult::fail(
                'Composer CLI',
                'Composer was not found on PATH.',
                'Install Composer from https://getcomposer.org.',
            );
        }

        return CheckResult::pass('Composer CLI', 'Available');
    }

    public function checkNode(): CheckResult
    {
        $major = $this->nodeMajorVersion();

        if ($major === null) {
            return CheckResult::fail(
                'Node.js',
                'Node.js is not installed or not accessible.',
                'Install Node.js 18+ from https://nodejs.org.',
            );
        }

        if ($major < 18) {
            return CheckResult::fail(
                'Node.js',
                "Node.js 18+ is required (v{$major} detected).",
                'Upgrade Node.js to v18, v20, or v22 LTS.',
            );
        }

        return CheckResult::pass('Node.js', "v{$major} detected");
    }

    public function checkPackageManager(): CheckResult
    {
        $manager = $this->detectNodePackageManager();

        if ($manager === null) {
            return CheckResult::warn(
                'Node Package Manager',
                'No lockfile found. npm will be used as default.',
                'Run npm install, pnpm install, yarn install, or bun install to generate a lockfile.',
            );
        }

        return CheckResult::pass('Node Package Manager', "{$manager} detected");
    }

    public function checkTailwind(): CheckResult
    {
        $major = $this->tailwindMajorVersion();

        if ($major === null) {
            return CheckResult::warn(
                'Tailwind CSS',
                'Tailwind CSS is not yet installed in package.json.',
                'BlatUI Admin will configure Tailwind CSS v4 automatically.',
            );
        }

        if ($major < 4) {
            return CheckResult::warn(
                'Tailwind CSS',
                "Tailwind CSS v{$major} detected. BlatUI requires Tailwind v4+.",
                'Run "npx @tailwindcss/upgrade" to migrate to Tailwind CSS v4.',
            );
        }

        return CheckResult::pass('Tailwind CSS', "v{$major} detected (v4+ compatible)");
    }

    public function checkLivewire(): CheckResult
    {
        if ($this->hasLivewire()) {
            return CheckResult::pass(
                'Livewire Runtime',
                'Livewire detected (bundled Alpine runtime will be adapted via alpine:init).',
            );
        }

        return CheckResult::pass(
            'Livewire Runtime',
            'Not installed (standard standalone Alpine bootstrap will be used).',
        );
    }

    public function checkFlux(): CheckResult
    {
        if ($this->hasFlux()) {
            return CheckResult::pass(
                'Flux UI Compatibility',
                'Flux detected (BlatUI will register with { theme: false } to avoid dark mode conflict).',
            );
        }

        return CheckResult::pass('Flux UI Compatibility', 'No conflict detected');
    }

    public function checkAlpine(): CheckResult
    {
        $hasAlpineInNode = $this->hasNodeDependency('alpinejs');
        $hasLivewire = $this->hasLivewire();

        if ($hasLivewire && $hasAlpineInNode) {
            return CheckResult::warn(
                'Alpine.js Dependency',
                'Both Livewire and npm alpinejs are present. Livewire already bundles Alpine.',
                'Consider removing alpinejs from package.json to prevent duplicate Alpine warnings.',
            );
        }

        return CheckResult::pass('Alpine.js Dependency', 'Adaptive runtime configuration ready');
    }

    public function checkPeerPackages(): CheckResult
    {
        $missing = [];

        if (! $this->hasComposerPackage('gehrisandro/tailwind-merge-laravel')) {
            $missing[] = 'gehrisandro/tailwind-merge-laravel';
        }

        if (! $this->hasComposerPackage('mallardduck/blade-lucide-icons')) {
            $missing[] = 'mallardduck/blade-lucide-icons';
        }

        if ($missing !== []) {
            return CheckResult::warn(
                'BlatUI Peer Packages',
                'Missing peer packages: '.implode(', ', $missing),
                'Run "composer require '.implode(' ', $missing).'"',
            );
        }

        return CheckResult::pass('BlatUI Peer Packages', 'Installed and discovered');
    }

    public function checkBlatUI(): CheckResult
    {
        if (! $this->hasComposerPackage('anousss007/blatui')) {
            return CheckResult::warn(
                'BlatUI Core Package',
                'anousss007/blatui is not detected in Composer.',
                'Run "composer require anousss007/blatui"',
            );
        }

        return CheckResult::pass('BlatUI Core Package', 'Installed');
    }

    public function fullDiagnostics(): DiagnosticReport
    {
        $report = new DiagnosticReport;

        $report->add($this->checkPhp());
        $report->add($this->checkComposer());
        $report->add($this->checkNode());
        $report->add($this->checkPackageManager());
        $report->add($this->checkTailwind());
        $report->add($this->checkLivewire());
        $report->add($this->checkFlux());
        $report->add($this->checkAlpine());
        $report->add($this->checkBlatUI());
        $report->add($this->checkPeerPackages());

        return $report;
    }

    public function hasComposerPackage(string $package): bool
    {
        $lockPath = $this->basePath('composer.lock');

        if (is_readable($lockPath)) {
            /** @var array{packages?: array<int, array{name?: string}>, packages-dev?: array<int, array{name?: string}>} $lock */
            $lock = json_decode((string) file_get_contents($lockPath), true) ?: [];

            foreach ($lock['packages'] ?? [] as $pkg) {
                if (($pkg['name'] ?? null) === $package) {
                    return true;
                }
            }

            foreach ($lock['packages-dev'] ?? [] as $pkg) {
                if (($pkg['name'] ?? null) === $package) {
                    return true;
                }
            }
        }

        $jsonPath = $this->basePath('composer.json');

        if (is_readable($jsonPath)) {
            /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $json */
            $json = json_decode((string) file_get_contents($jsonPath), true) ?: [];

            if (isset($json['require'][$package]) || isset($json['require-dev'][$package])) {
                return true;
            }
        }

        if ($this->basePath === $this->defaultBasePath && class_exists(InstalledVersions::class)) {
            try {
                return InstalledVersions::isInstalled($package);
            } catch (Throwable) {
                // Ignore and fall through
            }
        }

        return false;
    }

    public function hasLivewire(): bool
    {
        return $this->hasComposerPackage('livewire/livewire');
    }

    public function hasFlux(): bool
    {
        return $this->hasComposerPackage('livewire/flux');
    }

    /**
     * @return array<string, string>
     */
    public function nodeDependencies(): array
    {
        if ($this->nodeDependenciesCache !== null) {
            return $this->nodeDependenciesCache;
        }

        $path = $this->basePath('package.json');

        if (! is_readable($path)) {
            return $this->nodeDependenciesCache = [];
        }

        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $json */
        $json = json_decode((string) file_get_contents($path), true) ?: [];

        return $this->nodeDependenciesCache = [
            ...($json['devDependencies'] ?? []),
            ...($json['dependencies'] ?? []),
        ];
    }

    public function hasNodeDependency(string $package): bool
    {
        return isset($this->nodeDependencies()[$package]);
    }

    public function detectNodePackageManager(): ?string
    {
        $managers = [
            'bun' => ['bun.lockb', 'bun.lock'],
            'pnpm' => ['pnpm-lock.yaml'],
            'yarn' => ['yarn.lock'],
            'npm' => ['package-lock.json'],
        ];

        foreach ($managers as $manager => $lockFiles) {
            foreach ($lockFiles as $lockFile) {
                if (file_exists($this->basePath($lockFile))) {
                    return $manager;
                }
            }
        }

        $pkgJson = $this->basePath('package.json');

        if (is_readable($pkgJson)) {
            /** @var array{packageManager?: string} $json */
            $json = json_decode((string) file_get_contents($pkgJson), true) ?: [];
            $pm = explode('@', $json['packageManager'] ?? '')[0];

            if (isset($managers[$pm])) {
                return $pm;
            }
        }

        return null;
    }

    public function nodeMajorVersion(): ?int
    {
        try {
            $process = Process::timeout(10)->run(['node', '-v']);

            if (! $process->successful()) {
                return null;
            }

            $output = trim($process->output());

            if (preg_match('/v?(\d+)\.\d+/', $output, $matches) === 1) {
                $major = filter_var($matches[1], FILTER_VALIDATE_INT);

                return $major !== false ? $major : null;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function tailwindMajorVersion(): ?int
    {
        $deps = $this->nodeDependencies();

        if (! isset($deps['tailwindcss'])) {
            return null;
        }

        $clean = ltrim($deps['tailwindcss'], '^~>=<v ');
        $major = filter_var(explode('.', $clean)[0], FILTER_VALIDATE_INT);

        return $major !== false ? $major : null;
    }

    public function hasBinary(string $binary): bool
    {
        try {
            return Process::timeout(10)->run([$binary, '--version'])->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
