<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Actions\ArtisanRunner;
use BlatUI\Admin\Console\Installer\Actions\ProcessRunner;
use BlatUI\Admin\Console\Installer\Actions\StubManager;
use BlatUI\Admin\Console\Installer\Actions\ViteWiringManager;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use BlatUI\Admin\Console\Installer\Wizard\InstallContext;
use BlatUI\Admin\Console\Installer\Wizard\InstallWizard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/blatui_wizard_'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->detector = new EnvironmentDetector($this->tempDir);
    $this->processRunner = new ProcessRunner($this->detector, $this->tempDir);
    $this->viteManager = new ViteWiringManager($this->detector, $this->tempDir);
    $this->artisanRunner = new ArtisanRunner(fn () => 0);
    $this->stubManager = new StubManager($this->tempDir);
});

afterEach(function () {
    if (isset($this->tempDir)) {
        deleteDirectory($this->tempDir);
    }
});

it('completes dry run successfully without modifying system', function () {
    $wizard = new InstallWizard(
        $this->detector,
        $this->processRunner,
        $this->viteManager,
        $this->artisanRunner,
        $this->stubManager,
    );

    $context = new InstallContext(dryRun: true, interactive: false);
    $exitCode = $wizard->run($context);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(file_exists($this->tempDir.'/resources/css/app.css'))->toBeFalse()
        ->and(file_exists($this->tempDir.'/resources/js/app.js'))->toBeFalse();
});

it('aborts installation with failure exit code when critical diagnostics fail', function () {
    // Fake missing node binary
    Process::fake([
        '*node*' => Process::result('', 127),
    ]);

    $wizard = new InstallWizard(
        $this->detector,
        $this->processRunner,
        $this->viteManager,
        $this->artisanRunner,
        $this->stubManager,
    );

    $context = new InstallContext(dryRun: false, force: true, interactive: false);
    $exitCode = $wizard->run($context);

    expect($exitCode)->toBe(Command::FAILURE);
});

it('executes full installation pipeline non-interactively when requirements pass', function () {
    Process::fake([
        '*node*' => Process::result("v20.10.0\n", 0),
        '*composer*' => Process::result('Composer version 2.7.0', 0),
        '*npm*' => Process::result('packages installed', 0),
    ]);

    $artisanCalls = [];
    $artisanRunner = new ArtisanRunner(function (string $command, array $args) use (&$artisanCalls): int {
        $artisanCalls[] = [$command, $args];

        return 0;
    });

    $wizard = new InstallWizard(
        $this->detector,
        $this->processRunner,
        $this->viteManager,
        $artisanRunner,
        $this->stubManager,
    );

    $context = new InstallContext(
        dryRun: false,
        force: true,
        interactive: false,
        installApexCharts: true,
        selectedComponents: ['button', 'card', 'table'],
    );

    $exitCode = $wizard->run($context);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(file_exists($this->tempDir.'/resources/css/app.css'))->toBeTrue()
        ->and(file_exists($this->tempDir.'/resources/js/app.js'))->toBeTrue()
        ->and($artisanCalls)->toContain(['vendor:publish', ['--tag' => 'blatui-foundations', '--force' => true]])
        ->and($artisanCalls)->toContain(['vendor:publish', ['--tag' => 'blatui-admin', '--force' => true]])
        ->and($artisanCalls)->toContain(['blatui:add', ['components' => ['button', 'card', 'table'], '--force' => true]]);

    Process::assertRan(function ($process) {
        return in_array('composer', $process->command, true)
            && in_array('-W', $process->command, true);
    });
});
