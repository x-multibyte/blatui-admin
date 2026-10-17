<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Actions\ProcessRunner;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/blatui_runner_'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->detector = new EnvironmentDetector($this->tempDir);
});

afterEach(function () {
    if (isset($this->tempDir)) {
        deleteDirectory($this->tempDir);
    }
});

it('installs node packages using npm by default', function () {
    Process::fake([
        '*npm*install*' => Process::result('installed packages', 0),
    ]);

    $runner = new ProcessRunner($this->detector, $this->tempDir);
    $result = $runner->installNodePackages(['@alpinejs/anchor', '@alpinejs/focus'], dev: true);

    expect($result->successful())->toBeTrue();

    Process::assertRan(function ($process) {
        return $process->command === ['npm', 'install', '-D', '@alpinejs/anchor', '@alpinejs/focus'];
    });
});

it('installs node packages using pnpm when detected', function () {
    touch($this->tempDir.'/pnpm-lock.yaml');
    Process::fake([
        '*pnpm*add*' => Process::result('installed packages', 0),
    ]);

    $runner = new ProcessRunner($this->detector, $this->tempDir);
    $result = $runner->installNodePackages(['apexcharts'], dev: true);

    expect($result->successful())->toBeTrue();

    Process::assertRan(function ($process) {
        return $process->command === ['pnpm', 'add', '-D', 'apexcharts'];
    });
});

it('installs node packages using bun when detected', function () {
    touch($this->tempDir.'/bun.lock');
    Process::fake([
        '*bun*add*' => Process::result('installed packages', 0),
    ]);

    $runner = new ProcessRunner($this->detector, $this->tempDir);
    $result = $runner->installNodePackages(['@alpinejs/collapse'], dev: false);

    expect($result->successful())->toBeTrue();

    Process::assertRan(function ($process) {
        return $process->command === ['bun', 'add', '@alpinejs/collapse'];
    });
});

it('requires composer packages with memory limit and no-interaction', function () {
    Process::fake([
        '*composer*require*' => Process::result('installed', 0),
    ]);

    $runner = new ProcessRunner($this->detector, $this->tempDir);
    $result = $runner->requireComposerPackages(['livewire/livewire'], dev: false);

    expect($result->successful())->toBeTrue();

    Process::assertRan(function ($process) {
        return $process->command === ['composer', 'require', 'livewire/livewire', '--no-interaction']
            && ($process->environment['COMPOSER_MEMORY_LIMIT'] ?? null) === '-1';
    });
});

it('builds frontend assets using detected package manager', function () {
    touch($this->tempDir.'/yarn.lock');
    Process::fake([
        '*yarn*run*build*' => Process::result('built assets', 0),
    ]);

    $runner = new ProcessRunner($this->detector, $this->tempDir);
    $result = $runner->buildFrontendAssets();

    expect($result->successful())->toBeTrue();

    Process::assertRan(function ($process) {
        return $process->command === ['yarn', 'run', 'build'];
    });
});

it('executes npx commands', function () {
    Process::fake([
        '*npx*@tailwindcss/upgrade*' => Process::result('upgrade complete', 0),
    ]);

    $runner = new ProcessRunner($this->detector, $this->tempDir);
    $result = $runner->npx(['@tailwindcss/upgrade', '--force']);

    expect($result->successful())->toBeTrue()
        ->and($runner->getPlanForNpx(['@tailwindcss/upgrade', '--force']))
        ->toBe('npx @tailwindcss/upgrade --force');

    Process::assertRan(function ($process) {
        return $process->command === ['npx', '@tailwindcss/upgrade', '--force'];
    });
});

it('generates dry run plan descriptions', function () {
    touch($this->tempDir.'/pnpm-lock.yaml');
    $runner = new ProcessRunner($this->detector, $this->tempDir);

    expect($runner->getPlanForNodeInstall(['apexcharts'], dev: true))
        ->toBe('pnpm add -D apexcharts')
        ->and($runner->getPlanForComposerRequire(['livewire/livewire']))
        ->toBe('composer require livewire/livewire --no-interaction')
        ->and($runner->getPlanForFrontendBuild())
        ->toBe('pnpm run build')
        ->and($runner->getPlanForNpx(['@tailwindcss/upgrade']))
        ->toBe('npx @tailwindcss/upgrade');
});
