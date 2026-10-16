<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Commands\AdminInstallCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * Instantiate the install command bound to the testbench application.
 */
function installCommand(): AdminInstallCommand
{
    $command = new AdminInstallCommand;
    $command->setLaravel(app());
    app(Kernel::class)->resolve($command);

    return $command;
}

/**
 * Invoke a protected method on the install command.
 */
function callProtected(AdminInstallCommand $command, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionMethod($command, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($command, ...$arguments);
}

it('detects installed composer packages from vendor/composer/installed.json', function () {
    $command = installCommand();

    expect(callProtected($command, 'hasComposerPackage', ['x-multibyte/blatui-admin']))->toBeTrue()
        ->and(callProtected($command, 'hasComposerPackage', ['laravel/framework']))->toBeTrue()
        ->and(callProtected($command, 'hasComposerPackage', ['anousss007/blatui']))->toBeFalse();
});

it('detects the host laravel major version', function () {
    $command = installCommand();

    expect(callProtected($command, 'laravelMajorVersion'))
        ->toBe((int) explode('.', Application::VERSION)[0]);
});

it('merges dev and prod dependencies from package.json', function () {
    file_put_contents(base_path('package.json'), json_encode([
        'dependencies' => ['vite' => '^6.0.0'],
        'devDependencies' => ['alpinejs' => '^3.14.0'],
    ]));

    $command = installCommand();

    expect(callProtected($command, 'nodeDependencies'))
        ->toBe(['alpinejs' => '^3.14.0', 'vite' => '^6.0.0']);
});

it('returns an empty dependency list when package.json is missing', function () {
    $command = installCommand();

    expect(callProtected($command, 'nodeDependencies'))->toBe([]);
});

it('detects the node package manager from lock files', function (string $lockFile, string $manager) {
    touch(base_path('package.json'));
    file_put_contents(base_path($lockFile), '');

    $command = installCommand();

    expect(callProtected($command, 'detectNodePackageManager'))->toBe($manager);
})->with([
    ['package-lock.json', 'npm'],
    ['pnpm-lock.yaml', 'pnpm'],
    ['yarn.lock', 'yarn'],
    ['bun.lockb', 'bun'],
]);

it('falls back to the packageManager field when no lock file exists', function () {
    file_put_contents(base_path('package.json'), json_encode(['packageManager' => 'pnpm@9.0.0']));

    $command = installCommand();

    expect(callProtected($command, 'detectNodePackageManager'))->toBe('pnpm');
});

it('only reports npm dependencies that are not already installed', function () {
    file_put_contents(base_path('package.json'), json_encode([
        'devDependencies' => ['@alpinejs/collapse' => '^3.14.0', '@floating-ui/dom' => '^1.6.0'],
    ]));

    $command = installCommand();

    $required = callProtected($command, 'requiredNpmDependencies');
    $missing = callProtected($command, 'missingNpmDependencies');

    expect($required)->toContain('@alpinejs/anchor', '@alpinejs/collapse', '@alpinejs/focus', '@floating-ui/dom')
        ->and($missing)->not->toContain('@alpinejs/collapse')
        ->and($missing)->not->toContain('@floating-ui/dom')
        ->and($missing)->toContain('@alpinejs/anchor');
});

it('skips the standalone alpinejs dependency when livewire is installed', function () {
    $command = installCommand();

    if (! callProtected($command, 'hasComposerPackage', ['livewire/livewire'])) {
        test()->markTestSkipped('Livewire is not part of this test environment.');
    }

    expect(callProtected($command, 'requiredNpmDependencies'))->not->toContain('alpinejs');
});

it('exposes an environment-aware installation pipeline', function () {
    $command = installCommand();

    $steps = $command->steps();

    expect($steps)->toBeArray()
        ->and(array_map(fn ($step) => $step->title, $steps))
        ->toContain(
            'Install the BlatUI CLI (anousss007/blatui)',
            'Publish the BlatUI foundations (theme tokens + Alpine engine)',
            'Install the admin panel npm dependencies (Alpine.js plugins)',
            'Run database migrations',
        );
});

it('runs the full pipeline in dry-run mode without touching anything', function () {
    $this->artisan('admin:install --dry-run')
        ->expectsOutputToContain('Dry run finished.')
        ->assertSuccessful();
});
