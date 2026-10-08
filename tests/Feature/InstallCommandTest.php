<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(public_path('vendor/blatui-admin'));
});

afterEach(function () {
    File::deleteDirectory(public_path('vendor/blatui-admin'));
});

test('admin:install command migrates database and seeds default admin account', function () {
    $this->artisan('admin:install')
        ->expectsOutputToContain('BlatUI Admin installed successfully.')
        ->assertSuccessful();

    $admin = Administrator::where('username', 'admin')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Administrator');
});

test('admin:install publishes assets along with config and routes', function () {
    $this->artisan('admin:install')
        ->assertSuccessful();

    expect(File::exists(public_path('vendor/blatui-admin/admin.css')))->toBeTrue()
        ->and(File::exists(public_path('vendor/blatui-admin/admin.js')))->toBeTrue();
});
