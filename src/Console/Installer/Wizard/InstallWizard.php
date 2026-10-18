<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Wizard;

use BlatUI\Admin\Console\Installer\Actions\ArtisanRunner;
use BlatUI\Admin\Console\Installer\Actions\ProcessRunner;
use BlatUI\Admin\Console\Installer\Actions\StubManager;
use BlatUI\Admin\Console\Installer\Actions\ViteWiringManager;
use BlatUI\Admin\Console\Installer\Diagnostics\DiagnosticReport;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class InstallWizard
{
    public function __construct(
        public readonly EnvironmentDetector $detector,
        public readonly ProcessRunner $processRunner,
        public readonly ViteWiringManager $viteManager,
        public readonly ArtisanRunner $artisanRunner,
        public readonly StubManager $stubManager,
    ) {}

    public function run(InstallContext $context): int
    {
        // 1. Run environment diagnostics
        $report = $this->detector->fullDiagnostics();
        $this->renderDiagnostics($report);

        // 2. Abort on critical failures
        if ($report->hasCriticalFailures()) {
            foreach ($report->failures() as $failure) {
                $fix = $failure->suggestedFix !== null ? " (Fix: {$failure->suggestedFix})" : '';
                warning("Critical requirement failed: {$failure->name} - {$failure->message}{$fix}");
            }

            return Command::FAILURE;
        }

        // 3. Ask user preferences if in interactive mode
        $context = $this->askPreferences($context);

        // 4. If user cancelled during confirmation
        if ($context === null) {
            note('Installation cancelled.');

            return Command::SUCCESS;
        }

        // 5. Handle Dry Run Mode
        if ($context->dryRun) {
            $this->renderDryRunPlan($context);

            return Command::SUCCESS;
        }

        // 6. Execute Installation Pipeline
        return $this->executePipeline($context);
    }

    public function renderDiagnostics(DiagnosticReport $report): void
    {
        table(
            headers: ['Status', 'Check', 'Details'],
            rows: $report->toTableRows(),
        );
    }

    public function askPreferences(InstallContext $context): ?InstallContext
    {
        if (! $context->interactive || $context->dryRun || $context->force) {
            $components = $context->selectedComponents === []
                ? $this->stubManager->defaultAdminComponents()
                : $context->selectedComponents;

            return $context->withPreferences(selectedComponents: $components);
        }

        $confirmed = confirm(
            'BlatUI Admin will install required dependencies and scaffold admin assets. Continue?',
            default: true,
        );

        if (! $confirmed) {
            return null;
        }

        $installApexCharts = confirm(
            'Install ApexCharts for admin dashboard charts and widgets?',
            default: false,
        );

        /** @var array<int, string> $selectedComponents */
        $selectedComponents = multiselect(
            'Select BlatUI UI components to add to your app:',
            options: $this->stubManager->defaultAdminComponents(),
            default: $this->stubManager->defaultAdminComponents(),
        );

        return $context->withPreferences(
            installApexCharts: $installApexCharts,
            selectedComponents: $selectedComponents,
        );
    }

    /**
     * @return array<int, string>
     */
    public function requiredComposerPackages(): array
    {
        $packages = [
            'anousss007/blatui',
            'gehrisandro/tailwind-merge-laravel',
            'mallardduck/blade-lucide-icons',
        ];

        if (! $this->detector->hasLivewire()) {
            $packages[] = 'livewire/livewire';
        }

        return array_values(array_filter(
            $packages,
            fn (string $p): bool => ! $this->detector->hasComposerPackage($p),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function requiredNpmPackages(InstallContext $context): array
    {
        $packages = [
            '@alpinejs/anchor',
            '@alpinejs/collapse',
            '@alpinejs/focus',
            '@floating-ui/dom',
        ];

        // Greenfield apps without Alpine or Livewire need standalone alpinejs
        if (! $this->detector->hasLivewire() && ! $this->detector->hasNodeDependency('alpinejs')) {
            $packages[] = 'alpinejs';
        }

        if ($context->installApexCharts && ! $this->detector->hasNodeDependency('apexcharts')) {
            $packages[] = 'apexcharts';
        }

        return array_values(array_filter(
            $packages,
            fn (string $p): bool => ! $this->detector->hasNodeDependency($p),
        ));
    }

    public function renderDryRunPlan(InstallContext $context): void
    {
        note('--- [Dry Run Plan] ---');

        $composerPkgs = $this->requiredComposerPackages();

        if ($composerPkgs !== []) {
            note($this->processRunner->getPlanForComposerRequire(
                $composerPkgs,
                composerBinary: $context->composerPath,
                withAllDependencies: true,
            ));
        }

        note($this->artisanRunner->getPlanForPublish('blatui-foundations', force: $context->force));
        note($this->viteManager->getPlanForCSS());
        note($this->viteManager->getPlanForJS());

        $npmPkgs = $this->requiredNpmPackages($context);

        if ($npmPkgs !== []) {
            note($this->processRunner->getPlanForNodeInstall($npmPkgs, dev: true));
        }

        if ($context->selectedComponents !== []) {
            note($this->artisanRunner->getPlanForBlatuiAdd($context->selectedComponents, force: $context->force));
        }

        note($this->artisanRunner->getPlanForPublish('blatui-admin', force: $context->force));
        note($this->artisanRunner->getPlanForMigrate(fresh: $context->fresh));
        note($this->processRunner->getPlanForFrontendBuild());

        outro('Dry run finished. Remove --dry-run to execute the plan above.');
    }

    public function executePipeline(InstallContext $context): int
    {
        // 1. Install missing composer packages
        $composerPkgs = $this->requiredComposerPackages();

        if ($composerPkgs !== []) {
            $result = spin(
                fn () => $this->processRunner->requireComposerPackages(
                    $composerPkgs,
                    composerBinary: $context->composerPath,
                    withAllDependencies: true,
                ),
                'Installing required Composer packages…',
            );

            if (! $result->successful()) {
                warning('Failed to install Composer packages: '.$result->errorOutput());

                return Command::FAILURE;
            }
        }

        // 2. Publish BlatUI foundations
        $exitCode = spin(
            fn () => $this->artisanRunner->publish('blatui-foundations', force: $context->force),
            'Publishing BlatUI foundations…',
        );

        if ($exitCode !== 0) {
            warning('Failed to publish BlatUI foundations.');

            return Command::FAILURE;
        }

        // 3. Wire Vite CSS and JS
        $this->viteManager->wireCSS();
        $this->viteManager->wireJS();

        // 4. Install npm dependencies
        $npmPkgs = $this->requiredNpmPackages($context);

        if ($npmPkgs !== []) {
            $result = spin(
                fn () => $this->processRunner->installNodePackages($npmPkgs, dev: true),
                'Installing npm dependencies…',
            );

            if (! $result->successful()) {
                warning('Failed to install npm dependencies: '.$result->errorOutput());

                return Command::FAILURE;
            }
        }

        // 5. Add BlatUI components
        if ($context->selectedComponents !== []) {
            $exitCode = spin(
                fn () => $this->artisanRunner->blatuiAdd($context->selectedComponents, force: $context->force),
                'Adding BlatUI components…',
            );

            if ($exitCode !== 0) {
                warning('Failed to add BlatUI components.');

                return Command::FAILURE;
            }
        }

        // 6. Publish Admin resources
        $exitCode = spin(
            fn () => $this->artisanRunner->publish('blatui-admin', force: $context->force),
            'Publishing BlatUI Admin resources…',
        );

        if ($exitCode !== 0) {
            warning('Failed to publish BlatUI Admin resources.');

            return Command::FAILURE;
        }

        // 7. Run database migrations
        $exitCode = spin(
            fn () => $this->artisanRunner->migrate(fresh: $context->fresh),
            'Running database migrations…',
        );

        if ($exitCode !== 0) {
            warning('Failed to run database migrations.');

            return Command::FAILURE;
        }

        // 8. Build frontend assets
        $result = spin(
            fn () => $this->processRunner->buildFrontendAssets(),
            'Building frontend assets…',
        );

        if (! $result->successful()) {
            warning('Failed to build frontend assets.');

            return Command::FAILURE;
        }

        outro('BlatUI Admin installed successfully!');

        return Command::SUCCESS;
    }
}
