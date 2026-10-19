<?php

declare(strict_types=1);

use BlatUI\Admin\Admin;

it('resolves the singleton', function () {
    expect(app(Admin::class))->toBeInstanceOf(Admin::class);
});

it('returns the same instance from the container', function () {
    expect(app(Admin::class))->toBe(app(Admin::class));
});

it('merges the package config', function () {
    expect(config('blatui-admin.placeholder'))->toBe('default');
});

it('loads the package translations namespace', function () {
    expect(app('translator')->getLoader()->namespaces())->toHaveKey('blatui-admin');
});

it('loads the package views namespace', function () {
    expect(view()->getFinder()->getHints())->toHaveKey('blatui-admin');
});

it('registers the artisan command', function () {
    $this->artisan('blatui-admin:placeholder')
        ->expectsOutputToContain('Admin placeholder command executed.')
        ->assertSuccessful();
});
