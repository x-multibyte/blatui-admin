<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Actions\ArtisanRunner;
use BlatUI\Admin\Console\Installer\Actions\ProcessRunner;
use BlatUI\Admin\Console\Installer\Actions\StubManager;
use BlatUI\Admin\Console\Installer\Actions\ViteWiringManager;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use BlatUI\Admin\Console\Installer\Wizard\InstallContext;
use BlatUI\Admin\Console\Installer\Wizard\InstallWizard;
use Illuminate\Support\Facades\Artisan;

it('forwards all options to install wizard from admin:install alias', function () {
    $capturedContext = null;
    $spyWizard = new class(app(EnvironmentDetector::class), app(ProcessRunner::class), app(ViteWiringManager::class), app(ArtisanRunner::class), app(StubManager::class), $capturedContext) extends InstallWizard
    {
        public function __construct(
            EnvironmentDetector $detector,
            ProcessRunner $processRunner,
            ViteWiringManager $viteManager,
            ArtisanRunner $artisanRunner,
            StubManager $stubManager,
            public mixed &$contextRef,
        ) {
            parent::__construct($detector, $processRunner, $viteManager, $artisanRunner, $stubManager);
        }

        public function run(InstallContext $context): int
        {
            $this->contextRef = $context;

            return 0;
        }
    };

    app()->instance(InstallWizard::class, $spyWizard);

    $exitCode = Artisan::call('admin:install', [
        '--dry-run' => true,
        '--force' => true,
        '--fresh' => true,
        '--no-interaction' => true,
        '--composer-path' => '/custom/bin/composer',
        '--install-apex-charts' => true,
        '--components' => ['button', 'table'],
    ]);

    expect($exitCode)->toBe(0)
        ->and($capturedContext)->toBeInstanceOf(InstallContext::class)
        ->and($capturedContext->dryRun)->toBeTrue()
        ->and($capturedContext->force)->toBeTrue()
        ->and($capturedContext->fresh)->toBeTrue()
        ->and($capturedContext->interactive)->toBeFalse()
        ->and($capturedContext->composerPath)->toBe('/custom/bin/composer')
        ->and($capturedContext->installApexCharts)->toBeTrue()
        ->and($capturedContext->selectedComponents)->toBe(['button', 'table']);
});

it('executes dry-run via admin:install alias without errors', function () {
    $exitCode = Artisan::call('admin:install', [
        '--dry-run' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);
});
