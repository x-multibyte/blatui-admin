<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Actions\ArtisanRunner;

it('calls artisan commands and captures output with custom caller', function () {
    $recorded = [];
    $runner = new ArtisanRunner(function (string $command, array $arguments) use (&$recorded): int {
        $recorded[] = [$command, $arguments];

        return 0;
    });

    $exitCode = $runner->call('about');

    expect($exitCode)->toBe(0)
        ->and($recorded)->toHaveCount(1)
        ->and($recorded[0][0])->toBe('about');
});

it('publishes vendor tags with force option', function () {
    $recorded = [];
    $runner = new ArtisanRunner(function (string $command, array $arguments) use (&$recorded): int {
        $recorded[] = [$command, $arguments];

        return 0;
    });

    $exitCode = $runner->publish('blatui-foundations', force: true);

    expect($exitCode)->toBe(0)
        ->and($recorded)->toHaveCount(1)
        ->and($recorded[0][0])->toBe('vendor:publish')
        ->and($recorded[0][1])->toEqual([
            '--tag' => 'blatui-foundations',
            '--force' => true,
        ]);
});

it('runs standard migrations when fresh is false', function () {
    $recorded = [];
    $runner = new ArtisanRunner(function (string $command, array $arguments) use (&$recorded): int {
        $recorded[] = [$command, $arguments];

        return 0;
    });

    $exitCode = $runner->migrate(fresh: false);

    expect($exitCode)->toBe(0)
        ->and($recorded)->toHaveCount(1)
        ->and($recorded[0][0])->toBe('migrate')
        ->and($recorded[0][1])->toEqual(['--force' => true]);
});

it('runs migrate:fresh when fresh is true', function () {
    $recorded = [];
    $runner = new ArtisanRunner(function (string $command, array $arguments) use (&$recorded): int {
        $recorded[] = [$command, $arguments];

        return 0;
    });

    $exitCode = $runner->migrate(fresh: true);

    expect($exitCode)->toBe(0)
        ->and($recorded)->toHaveCount(1)
        ->and($recorded[0][0])->toBe('migrate:fresh')
        ->and($recorded[0][1])->toEqual(['--force' => true]);
});

it('calls blatui:add for adding components', function () {
    $recorded = [];
    $runner = new ArtisanRunner(function (string $command, array $arguments) use (&$recorded): int {
        $recorded[] = [$command, $arguments];

        return 0;
    });

    $exitCode = $runner->blatuiAdd(['button', 'card'], force: false);

    expect($exitCode)->toBe(0)
        ->and($recorded)->toHaveCount(1)
        ->and($recorded[0][0])->toBe('blatui:add')
        ->and($recorded[0][1])->toEqual([
            'components' => ['button', 'card'],
            '--force' => false,
        ]);
});

it('generates dry-run plans for artisan commands', function () {
    $runner = new ArtisanRunner;

    expect($runner->getPlanForPublish('blatui-foundations', force: false))
        ->toBe('php artisan vendor:publish --tag=blatui-foundations')
        ->and($runner->getPlanForPublish('blatui-admin', force: true))
        ->toBe('php artisan vendor:publish --tag=blatui-admin --force')
        ->and($runner->getPlanForMigrate(fresh: false))
        ->toBe('php artisan migrate --force')
        ->and($runner->getPlanForMigrate(fresh: true))
        ->toBe('php artisan migrate:fresh --force')
        ->and($runner->getPlanForBlatuiAdd(['button', 'card']))
        ->toBe('php artisan blatui:add button card');
});
