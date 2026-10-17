<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Wizard\StepResult;

it('creates success result', function () {
    $result = StepResult::success('Install packages', 'All packages installed');

    expect($result->title)->toBe('Install packages')
        ->and($result->successful)->toBeTrue()
        ->and($result->message)->toBe('All packages installed')
        ->and($result->skipped)->toBeFalse()
        ->and($result->plan)->toBeNull();
});

it('creates failure result', function () {
    $result = StepResult::failure('Build assets', 'Vite build failed with exit code 1');

    expect($result->title)->toBe('Build assets')
        ->and($result->successful)->toBeFalse()
        ->and($result->message)->toBe('Vite build failed with exit code 1')
        ->and($result->skipped)->toBeFalse();
});

it('creates skipped result with reason', function () {
    $result = StepResult::skip('Livewire install', 'Already installed in host app');

    expect($result->title)->toBe('Livewire install')
        ->and($result->successful)->toBeTrue()
        ->and($result->skipped)->toBeTrue()
        ->and($result->skipReason)->toBe('Already installed in host app');
});

it('creates plan result for dry-run', function () {
    $result = StepResult::plan('Publish foundations', 'php artisan vendor:publish --tag=blatui-foundations');

    expect($result->title)->toBe('Publish foundations')
        ->and($result->successful)->toBeTrue()
        ->and($result->plan)->toBe('php artisan vendor:publish --tag=blatui-foundations');
});
