<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Actions;

use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

final class ProcessRunner
{
    private EnvironmentDetector $detector;

    private string $basePath;

    public function __construct(
        ?EnvironmentDetector $detector = null,
        ?string $basePath = null,
    ) {
        $this->basePath = rtrim($basePath ?? (function_exists('base_path') ? base_path() : (getcwd() ?: '.')), '/');
        $this->detector = $detector ?? new EnvironmentDetector($this->basePath);
    }

    /**
     * @param  array<int, string>  $packages
     */
    public function installNodePackages(array $packages, bool $dev = true): ProcessResult
    {
        $command = $this->buildNodeInstallCommand($packages, $dev);

        return $this->run($command, timeout: 300);
    }

    /**
     * @param  array<int, string>  $packages
     */
    public function requireComposerPackages(
        array $packages,
        bool $dev = false,
        ?string $composerBinary = null,
        bool $withAllDependencies = false,
    ): ProcessResult {
        $command = $this->buildComposerRequireCommand($packages, $dev, $composerBinary, $withAllDependencies);

        return $this->run(
            $command,
            timeout: 300,
            env: ['COMPOSER_MEMORY_LIMIT' => '-1'],
        );
    }

    public function buildFrontendAssets(): ProcessResult
    {
        $manager = $this->resolvedPackageManager();

        return $this->run([$manager, 'run', 'build'], timeout: 300);
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    public function npx(array $command, ?int $timeout = 60, array $env = []): ProcessResult
    {
        return $this->run(['npx', ...$command], $timeout, $env);
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    public function run(array $command, ?int $timeout = 60, array $env = []): ProcessResult
    {
        $pending = Process::path($this->basePath);

        if ($timeout !== null) {
            $pending->timeout($timeout);
        } else {
            $pending->forever();
        }

        if ($env !== []) {
            $pending->env($env);
        }

        return $pending->run($command);
    }

    /**
     * @param  array<int, string>  $packages
     * @return array<int, string>
     */
    public function buildNodeInstallCommand(array $packages, bool $dev = true): array
    {
        $manager = $this->resolvedPackageManager();

        $args = match ($manager) {
            'pnpm', 'yarn', 'bun' => $dev ? [$manager, 'add', '-D'] : [$manager, 'add'],
            default => $dev ? ['npm', 'install', '-D'] : ['npm', 'install'],
        };

        return [...$args, ...$packages];
    }

    /**
     * @param  array<int, string>  $packages
     * @return array<int, string>
     */
    public function buildComposerRequireCommand(
        array $packages,
        bool $dev = false,
        ?string $composerBinary = null,
        bool $withAllDependencies = false,
    ): array {
        $binary = $composerBinary ?? 'composer';
        $args = [$binary, 'require', ...$packages];

        if ($dev) {
            $args[] = '--dev';
        }

        if ($withAllDependencies) {
            $args[] = '-W';
        }

        $args[] = '--no-interaction';

        return $args;
    }

    /**
     * @param  array<int, string>  $packages
     */
    public function getPlanForNodeInstall(array $packages, bool $dev = true): string
    {
        return implode(' ', $this->buildNodeInstallCommand($packages, $dev));
    }

    /**
     * @param  array<int, string>  $packages
     */
    public function getPlanForComposerRequire(
        array $packages,
        bool $dev = false,
        ?string $composerBinary = null,
        bool $withAllDependencies = false,
    ): string {
        return implode(' ', $this->buildComposerRequireCommand($packages, $dev, $composerBinary, $withAllDependencies));
    }

    public function getPlanForFrontendBuild(): string
    {
        $manager = $this->resolvedPackageManager();

        return "{$manager} run build";
    }

    /**
     * @param  array<int, string>  $command
     */
    public function getPlanForNpx(array $command): string
    {
        return 'npx '.implode(' ', $command);
    }

    private function resolvedPackageManager(): string
    {
        return $this->detector->detectNodePackageManager() ?? 'npm';
    }
}
