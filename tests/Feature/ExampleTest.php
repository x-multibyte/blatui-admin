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
    expect(config('blatui-admin.name'))->toBe('BlatUI Admin');
});

it('loads the package translations', function () {
    expect(trans('blatui-admin::messages.placeholder'))->toBe('Admin placeholder translation.');
});

it('resolves the placeholder translation under zh_CN', function () {
    app()->setLocale('zh_CN');

    expect(trans('blatui-admin::messages.placeholder'))->toBe('后台占位翻译。');
});

it('loads the package views', function () {
    expect(view()->exists('blatui-admin::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('admin')
        ->expectsOutputToContain('BlatUI Admin')
        ->assertSuccessful();
});

it('registers the admin:list command', function () {
    $this->artisan('admin:list')
        ->expectsOutputToContain('BlatUI Admin')
        ->assertSuccessful();
});
