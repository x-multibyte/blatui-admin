<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Helpers for inspecting the host application's environment (Composer, Node,
 * installed packages) so installers can adapt to each project.
 */
trait InteractsWithEnvironment
{
    /**
     * @var array<string, bool>|null
     */
    private ?array $composerPackagesCache = null;

    /**
     * All packages currently installed in the host app (name => true).
     *
     * Reads `vendor/composer/installed.json` when available and falls back
     * to walking `composer.lock`.
     *
     * @return array<string, bool>
     */
    protected function composerPackages(): array
    {
        if ($this->composerPackagesCache !== null) {
            return $this->composerPackagesCache;
        }

        $packages = [];

        $installedJson = base_path('vendor/composer/installed.json');

        if (is_readable($installedJson)) {
            /** @var array{packages?: array<int, array{name?: string}>} $decoded */
            $decoded = json_decode((string) file_get_contents($installedJson), true) ?: [];

            foreach ($decoded['packages'] ?? [] as $package) {
                if (isset($package['name'])) {
                    $packages[$package['name']] = true;
                }
            }
        } else {
            $lockPath = base_path('composer.lock');

            if (is_readable($lockPath)) {
                /** @var array{packages?: array<int, array{name?: string}>, packages-dev?: array<int, array{name?: string}>} $lock */
                $lock = json_decode((string) file_get_contents($lockPath), true) ?: [];

                foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
                    if (isset($package['name'])) {
                        $packages[$package['name']] = true;
                    }
                }
            }
        }

        return $this->composerPackagesCache = $packages;
    }

    protected function hasComposerPackage(string $package): bool
    {
        return isset($this->composerPackages()[$package]);
    }

    /**
     * The host Laravel major version (e.g. 12), or null when undetectable.
     */
    protected function laravelMajorVersion(): ?int
    {
        return (int) explode('.', Application::VERSION)[0];
    }

    /**
     * Whether the given console command is registered in the host app.
     */
    protected function hasArtisanCommand(string $name): bool
    {
        /** @var Kernel $kernel */
        $kernel = $this->laravel->make(Kernel::class);

        try {
            $kernel->bootstrap();
        } catch (Throwable) {
            // The application may already be bootstrapped; ignore.
        }

        if (method_exists($kernel, 'has')) {
            /** @phpstan-ignore-next-line method.notFound */
            return (bool) $kernel->has($name);
        }

        // Fallback for kernels that only expose the command list (all()).
        return array_key_exists($name, $kernel->all());
    }

    /**
     * Whether the given binary can be executed on this machine.
     */
    protected function hasBinary(string $binary): bool
    {
        return Process::timeout(10)->run([$binary, '--version'])->successful();
    }

    /**
     * The installed package versions map from the host app's composer.lock
     * (name => version), used to detect e.g. the Livewire major version.
     *
     * @return array<string, string>
     */
    protected function lockedVersions(): array
    {
        $lockPath = base_path('composer.lock');

        if (! is_readable($lockPath)) {
            return [];
        }

        /** @var array{packages?: array<int, array{name?: string, version?: string}>, packages-dev?: array<int, array{name?: string, version?: string}>} $lock */
        $lock = json_decode((string) file_get_contents($lockPath), true) ?: [];

        $versions = [];

        foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
            if (isset($package['name'], $package['version'])) {
                $versions[$package['name']] = ltrim($package['version'], 'v');
            }
        }

        return $versions;
    }

    /**
     * Read the host app's package.json dependencies (merged dev + prod).
     *
     * @return array<string, string>
     */
    protected function nodeDependencies(): array
    {
        $path = base_path('package.json');

        if (! is_readable($path)) {
            return [];
        }

        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $json */
        $json = json_decode((string) file_get_contents($path), true) ?: [];

        return [...($json['devDependencies'] ?? []), ...($json['dependencies'] ?? [])];
    }

    protected function hasNodeDependency(string $package): bool
    {
        return isset($this->nodeDependencies()[$package]);
    }

    /**
     * Detect which package manager the host app uses (bun / pnpm / yarn / npm).
     */
    protected function detectNodePackageManager(): ?string
    {
        $managers = [
            'bun' => ['bun.lockb', 'bun.lock'],
            'pnpm' => ['pnpm-lock.yaml'],
            'yarn' => ['yarn.lock'],
            'npm' => ['package-lock.json'],
        ];

        foreach ($managers as $manager => $lockFiles) {
            foreach ($lockFiles as $lockFile) {
                if (file_exists(base_path($lockFile))) {
                    return $manager;
                }
            }
        }

        if (is_readable(base_path('package.json'))) {
            /** @var array{packageManager?: string} $json */
            $json = json_decode((string) file_get_contents(base_path('package.json')), true) ?: [];

            foreach (array_keys($managers) as $manager) {
                if (str_starts_with($json['packageManager'] ?? '', $manager.'@')) {
                    return $manager;
                }
            }

            return 'npm';
        }

        return null;
    }
}
