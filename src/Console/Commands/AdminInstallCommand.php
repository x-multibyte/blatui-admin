<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use BlatUI\Admin\Console\Commands\Concerns\InteractsWithEnvironment;
use BlatUI\Admin\Support\InstallStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'admin:install')]
class AdminInstallCommand extends Command
{
    use InteractsWithEnvironment;

    protected $signature = 'admin:install
                            {--dry-run : Show the environment-aware installation plan without changing anything}
                            {--fresh : Drop all tables and re-run the migrations from scratch}
                            {--force : Re-run steps even when they appear to be already installed}
                            {--composer-path= : Path to the Composer binary, when it is not on PATH}';

    protected $description = 'Install the BlatUI Admin panel and every prerequisite it needs, adapted to your project environment.';

    /**
     * Whether the command should only report what it would do.
     */
    protected bool $dryRun = false;

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if (! $this->dryRun && ! $this->option('force') && ! confirm(
            'BlatUI Admin will inspect your project and install everything it needs (Composer packages, BlatUI foundations, npm dependencies, config, views, migrations). Continue?',
            true,
        )) {
            note('Installation cancelled.');

            return self::SUCCESS;
        }

        foreach ($this->steps() as $step) {
            if (! $step->shouldRun($this)) {
                $this->components->info("[skip] {$step->title}");

                continue;
            }

            if ($this->dryRun) {
                $this->components->warn("[plan] {$step->title}");

                if (($plan = $step->plan($this)) !== null) {
                    note($plan);
                }

                continue;
            }

            $this->components->info("→ {$step->title}");

            $result = $step->run($this);

            if ($result === self::FAILURE) {
                warning("Step \"{$step->title}\" failed. Fix the problem above and run admin:install again — completed steps are skipped automatically.");

                return self::FAILURE;
            }
        }

        if ($this->dryRun) {
            outro('Dry run finished. Remove --dry-run to execute the plan above.');

            return self::SUCCESS;
        }

        $message = 'BlatUI Admin is installed.';

        try {
            $message .= ' Visit '.route('blatui-admin.dashboard', absolute: false).' to get started.';
        } catch (Throwable) {
            // The dashboard route may not be registered yet in a bare app.
        }

        outro($message);

        return self::SUCCESS;
    }

    /**
     * The environment-aware installation pipeline.
     *
     * @return array<int, InstallStep>
     */
    public function steps(): array
    {
        return [
            new InstallStep(
                title: 'Check requirements (PHP 8.3+, Composer, Node 18+)',
                shouldRun: fn (): bool => true,
                run: fn (): int => $this->checkRequirements(),
                plan: fn (): string => 'Verify php -v, composer -V and node -v are available.',
            ),
            new InstallStep(
                title: 'Install Livewire (livewire/livewire)',
                shouldRun: fn (): bool => ! $this->hasComposerPackage('livewire/livewire'),
                run: fn (): int => $this->requireComposerPackages('livewire/livewire'),
                plan: fn (): string => 'composer require livewire/livewire',
            ),
            new InstallStep(
                title: 'Install the BlatUI CLI (anousss007/blatui)',
                shouldRun: fn (): bool => ! $this->hasComposerPackage('anousss007/blatui'),
                run: fn (): int => $this->requireComposerPackages('anousss007/blatui'),
                plan: fn (): string => 'composer require anousss007/blatui',
            ),
            new InstallStep(
                title: 'Install BlatUI peer packages (tailwind-merge-laravel, blade-lucide-icons)',
                shouldRun: fn (): bool => ! $this->hasComposerPackage('gehrisandro/tailwind-merge-laravel')
                    || ! $this->hasComposerPackage('mallardduck/blade-lucide-icons'),
                run: fn (): int => $this->requireComposerPackages(
                    'gehrisandro/tailwind-merge-laravel',
                    'mallardduck/blade-lucide-icons',
                ),
                plan: fn (): string => 'composer require gehrisandro/tailwind-merge-laravel mallardduck/blade-lucide-icons',
            ),
            new InstallStep(
                title: 'Publish the BlatUI foundations (theme tokens + Alpine engine)',
                shouldRun: fn (): bool => $this->hasComposerPackage('anousss007/blatui')
                    && $this->hasArtisanCommand('vendor:publish')
                    && (! file_exists(resource_path('css/blatui.css'))
                        || ! file_exists(resource_path('js/blatui-core.js'))
                        || $this->option('force')),
                run: fn (): int => (int) $this->callSilently('vendor:publish', [
                    '--tag' => 'blatui-foundations',
                    '--force' => (bool) $this->option('force'),
                ]) === 0 ? self::SUCCESS : self::FAILURE,
                plan: fn (): string => 'php artisan vendor:publish --tag=blatui-foundations',
            ),
            new InstallStep(
                title: 'Wire the BlatUI foundations into Vite entrypoints (resources/css/app.css, resources/js/app.js)',
                shouldRun: fn (): bool => $this->hasNodeDependency('vite') || file_exists(base_path('vite.config.js')) || file_exists(base_path('vite.config.ts')),
                run: fn (): int => $this->wireViteEntrypoints(),
                plan: fn (): string => <<<'PLAN'
                    Add to resources/css/app.css:   @import "./blatui.css";
                    Add to resources/js/app.js:     import "./blatui.js";
                    (If your app already boots Alpine or uses Livewire, blatui-core.js is registered instead — see the BlatUI docs.)
                    PLAN,
            ),
            new InstallStep(
                title: 'Install the admin panel npm dependencies (Alpine.js plugins)',
                shouldRun: fn (): bool => $this->npmDependenciesMissing(),
                run: fn (): int => $this->installNpmDependencies(),
                plan: fn (): string => $this->npmInstallCommand().' -D '.$this->missingNpmDependenciesString(),
            ),
            new InstallStep(
                title: 'Publish BlatUI Admin resources (config, views, migrations, assets, lang)',
                shouldRun: fn (): bool => true,
                run: fn (): int => (int) $this->callSilently('vendor:publish', [
                    '--tag' => 'blatui-admin',
                    '--force' => (bool) $this->option('force'),
                ]) === 0 ? self::SUCCESS : self::FAILURE,
                plan: fn (): string => 'php artisan vendor:publish --tag=blatui-admin',
            ),
            new InstallStep(
                title: 'Run database migrations',
                shouldRun: fn (): bool => $this->adminMigrationsPending(),
                run: fn (): int => (int) $this->call('migrate', $this->option('fresh') ? ['--force' => true] : []) === 0 ? self::SUCCESS : self::FAILURE,
                plan: fn (): string => $this->option('fresh') ? 'php artisan migrate --force' : 'php artisan migrate',
            ),
            new InstallStep(
                title: 'Build frontend assets',
                shouldRun: fn (): bool => $this->hasNodeDependency('vite') || file_exists(base_path('vite.config.js')) || file_exists(base_path('vite.config.ts')),
                run: fn (): int => $this->buildAssets(),
                plan: fn (): string => $this->detectNodePackageManager().'/npm run build (skipped when node_modules is missing)',
            ),
            new InstallStep(
                title: 'Verify the setup (php artisan blatui:init)',
                shouldRun: fn (): bool => $this->hasArtisanCommand('blatui:init'),
                run: fn (): int => (int) $this->call('blatui:init') === 0 ? self::SUCCESS : self::FAILURE,
                plan: fn (): string => 'php artisan blatui:init',
            ),
        ];
    }

    /**
     * Ensure the host machine can actually run the installer.
     */
    protected function checkRequirements(): int
    {
        $failures = [];

        // Runtime guard: the package itself requires 8.3, but a host app may
        // have been generated with an older toolchain before upgrading.
        if (filter_var((string) PHP_VERSION_ID, FILTER_VALIDATE_INT) < 80300) {
            $failures[] = 'PHP 8.3 or newer is required ('.PHP_VERSION.' detected).';
        }

        if (! $this->hasBinary('composer')) {
            $failures[] = 'Composer was not found on this machine.';
        }

        if (! $this->hasBinary('node')) {
            $failures[] = 'Node.js 18+ was not found. BlatUI components need Tailwind CSS v4 + Alpine.js built through Vite.';
        } elseif (($nodeMajor = $this->nodeMajorVersion()) !== null && $nodeMajor < 18) {
            $failures[] = "Node.js 18 or newer is required (v{$nodeMajor} detected).";
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                warning($failure);
            }

            return self::FAILURE;
        }

        if ($this->hasNodeDependency('tailwindcss')) {
            $tailwindMajor = $this->tailwindMajorVersion();

            if ($tailwindMajor !== null && $tailwindMajor < 4) {
                warning("Tailwind CSS v{$tailwindMajor} detected. BlatUI requires Tailwind CSS v4+. Run 'npx @tailwindcss/upgrade' to upgrade.");
            }
        }

        note('Laravel '.$this->laravelMajorVersion().' · PHP '.PHP_VERSION.' · Node '.$this->nodeVersion().' · package manager: '.($this->detectNodePackageManager() ?? 'none'));

        return self::SUCCESS;
    }

    protected function nodeVersion(): string
    {
        return trim(Process::timeout(10)->run(['node', '-v'])->output());
    }

    /**
     * Run `composer require` for the given packages.
     */
    protected function requireComposerPackages(string ...$packages): int
    {
        $binary = $this->option('composer-path') ?: $this->findComposer();

        if ($binary === null) {
            warning('Composer is not available; install these packages manually: '.implode(', ', $packages));

            return self::FAILURE;
        }

        /** @var array<int, string> $argv */
        $argv = [$binary, 'require', ...$packages, '--no-interaction'];

        $result = spin(
            fn () => Process::forever()->env(['COMPOSER_MEMORY_LIMIT' => '-1'])->run($argv),
            'Installing '.implode(', ', $packages).'…',
        );

        // New packages are only visible to the current process after a
        // reload of the cached package list.
        $this->composerPackagesCache = null;

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }

    protected function findComposer(): ?string
    {
        foreach (['composer', 'composer.phar'] as $candidate) {
            if ($this->hasBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Append the BlatUI foundation imports to the host app's Vite entrypoints,
     * leaving existing files untouched apart from the additive import lines.
     */
    protected function wireViteEntrypoints(): int
    {
        $cssEntry = resource_path('css/app.css');
        $jsEntry = resource_path('js/app.js');

        $livewireInstalled = $this->hasComposerPackage('livewire/livewire');

        if (! file_exists($cssEntry)) {
            if (! is_dir(dirname($cssEntry))) {
                mkdir(dirname($cssEntry), 0755, true);
            }
            file_put_contents($cssEntry, '@import "tailwindcss";'."\n".'@import "./blatui.css";'."\n");
            note('Created resources/css/app.css with Tailwind CSS and BlatUI imports');
        } elseif (! str_contains((string) file_get_contents($cssEntry), 'blatui.css')) {
            file_put_contents($cssEntry, "\n".'@import "./blatui.css";'."\n", FILE_APPEND);
            note('Added @import "./blatui.css"; to resources/css/app.css');
        }

        if (! file_exists($jsEntry)) {
            if (! is_dir(dirname($jsEntry))) {
                mkdir(dirname($jsEntry), 0755, true);
            }

            if ($livewireInstalled) {
                file_put_contents($jsEntry, <<<'JS'
import { registerBlatUI } from './blatui-core.js'

document.addEventListener('alpine:init', () => {
    registerBlatUI(window.Alpine)
})

JS);
                note('Created resources/js/app.js and registered BlatUI onto Livewire\'s Alpine');
            } else {
                file_put_contents($jsEntry, 'import "./blatui.js";'."\n");
                note('Created resources/js/app.js with BlatUI bootstrap');
            }
        } else {
            $contents = (string) file_get_contents($jsEntry);

            if ($livewireInstalled) {
                // Livewire bundles and starts Alpine itself: register BlatUI
                // onto Livewire's Alpine through the alpine:init hook instead
                // of booting a second copy with blatui.js.
                if (! str_contains($contents, 'blatui-core.js')) {
                    file_put_contents($jsEntry, "\n".<<<'JS'
import { registerBlatUI } from './blatui-core.js'

document.addEventListener('alpine:init', () => {
    registerBlatUI(window.Alpine)
})
JS."\n", FILE_APPEND);
                    note('Registered BlatUI onto Livewire\'s Alpine in resources/js/app.js');
                }
            } elseif (! str_contains($contents, 'blatui.js')) {
                file_put_contents($jsEntry, "\n".'import "./blatui.js";'."\n", FILE_APPEND);
                note('Added import "./blatui.js"; to resources/js/app.js');
            }
        }

        return self::SUCCESS;
    }

    /**
     * npm dev dependencies required by the admin panel UI.
     *
     * @return array<int, string>
     */
    protected function requiredNpmDependencies(): array
    {
        $required = [
            '@alpinejs/anchor',
            '@alpinejs/collapse',
            '@alpinejs/focus',
            '@floating-ui/dom',
            'apexcharts',
        ];

        // Only greenfield apps without an existing Alpine instance need the
        // standalone alpinejs package (Livewire / custom setups bundle it).
        if (! $this->hasComposerPackage('livewire/livewire') && ! $this->hasNodeDependency('alpinejs')) {
            $required[] = 'alpinejs';
        }

        return $required;
    }

    /**
     * @return array<int, string>
     */
    protected function missingNpmDependencies(): array
    {
        return array_values(array_filter(
            $this->requiredNpmDependencies(),
            fn (string $package): bool => ! $this->hasNodeDependency($package),
        ));
    }

    protected function missingNpmDependenciesString(): string
    {
        return implode(' ', $this->missingNpmDependencies());
    }

    protected function npmDependenciesMissing(): bool
    {
        return $this->missingNpmDependencies() !== [] && $this->detectNodePackageManager() !== null;
    }

    protected function npmInstallCommand(): string
    {
        return match ($manager = $this->detectNodePackageManager()) {
            'pnpm' => 'pnpm add',
            'yarn' => 'yarn add',
            'bun' => 'bun add',
            default => ($manager ?? 'npm').' install',
        };
    }

    protected function installNpmDependencies(): int
    {
        $manager = $this->detectNodePackageManager();

        if ($manager === null || ! $this->hasBinary($manager)) {
            warning('No usable Node package manager found. Install these dev dependencies manually: '.$this->missingNpmDependenciesString());

            return self::FAILURE;
        }

        $arguments = match ($manager) {
            'pnpm', 'yarn', 'bun' => [$manager, 'add', '-D'],
            default => ['npm', 'install', '-D'],
        };

        $result = spin(
            fn () => Process::forever()->path(base_path())->run([
                ...$arguments,
                ...$this->missingNpmDependencies(),
            ]),
            'Installing npm dependencies…',
        );

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Whether any published admin migration still needs to run.
     */
    protected function adminMigrationsPending(): bool
    {
        if ($this->option('fresh')) {
            return true;
        }

        $published = glob(database_path('migrations/*blatui_admin*')) ?: [];

        // Also consider the package's own (unpublished) migrations, which
        // Laravel loads automatically when using `php artisan migrate`.
        if ($published === []) {
            $published = glob(__DIR__.'/../../../database/migrations/*.php') ?: [];
        }

        try {
            /** @var array<int, string> $migrations */
            $migrations = DB::table('migrations')->pluck('migration')->all();
        } catch (Throwable) {
            return true;
        }

        foreach ($published as $file) {
            $name = basename($file, '.php');

            if (! in_array($name, $migrations, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Install node_modules if missing, then build production assets.
     */
    protected function buildAssets(): int
    {
        $manager = $this->detectNodePackageManager();

        if ($manager === null || ! $this->hasBinary($manager)) {
            warning('Skipping asset build: no Node package manager available. Run "npm run build" manually once Node is installed.');

            return self::SUCCESS;
        }

        if (! is_dir(base_path('node_modules'))) {
            $install = match ($manager) {
                'pnpm' => ['pnpm', 'install'],
                'yarn' => ['yarn', 'install'],
                'bun' => ['bun', 'install'],
                default => ['npm', 'install'],
            };

            $result = spin(
                fn () => Process::forever()->path(base_path())->run($install),
                'Installing node_modules…',
            );

            if (! $result->successful()) {
                return self::FAILURE;
            }
        }

        $result = spin(
            fn () => Process::forever()->path(base_path())->run([$manager, 'run', 'build']),
            'Building frontend assets…',
        );

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
