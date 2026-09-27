<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;

test('admin:install command migrates database and seeds default admin account', function () {
    $this->artisan('admin:install')
        ->expectsOutputToContain('BlatUI Admin installed successfully.')
        ->assertSuccessful();

    $admin = Administrator::where('username', 'admin')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Administrator');
});
