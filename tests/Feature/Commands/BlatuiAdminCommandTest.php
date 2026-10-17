<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('runs blatui:admin info when install option is not passed', function () {
    $exitCode = Artisan::call('blatui:admin');

    expect($exitCode)->toBe(0);
});

it('executes blatui:admin --install in dry-run mode successfully', function () {
    $exitCode = Artisan::call('blatui:admin', [
        '--install' => true,
        '--dry-run' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);
});

it('supports command options for apexcharts and components', function () {
    $exitCode = Artisan::call('blatui:admin', [
        '--install' => true,
        '--dry-run' => true,
        '--no-interaction' => true,
        '--install-apex-charts' => true,
        '--components' => ['button', 'card'],
    ]);

    expect($exitCode)->toBe(0);
});
