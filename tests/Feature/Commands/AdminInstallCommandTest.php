<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('executes admin:install alias in dry-run mode successfully', function () {
    $exitCode = Artisan::call('admin:install', [
        '--dry-run' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);
});

it('forwards options to install wizard from admin:install alias', function () {
    $exitCode = Artisan::call('admin:install', [
        '--dry-run' => true,
        '--force' => true,
        '--fresh' => true,
        '--no-interaction' => true,
        '--install-apex-charts' => true,
        '--components' => ['button', 'table'],
    ]);

    expect($exitCode)->toBe(0);
});
