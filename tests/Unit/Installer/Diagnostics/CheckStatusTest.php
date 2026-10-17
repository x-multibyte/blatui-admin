<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Diagnostics\CheckStatus;

it('has pass, warn, and fail cases', function () {
    expect(CheckStatus::cases())->toHaveCount(3)
        ->and(CheckStatus::PASS->name)->toBe('PASS')
        ->and(CheckStatus::WARN->name)->toBe('WARN')
        ->and(CheckStatus::FAIL->name)->toBe('FAIL');
});

it('provides human-readable labels', function () {
    expect(CheckStatus::PASS->label())->toBe('PASS')
        ->and(CheckStatus::WARN->label())->toBe('WARN')
        ->and(CheckStatus::FAIL->label())->toBe('FAIL');
});

it('provides terminal icons for tables', function () {
    expect(CheckStatus::PASS->icon())->toBe('✔')
        ->and(CheckStatus::WARN->icon())->toBe('⚠')
        ->and(CheckStatus::FAIL->icon())->toBe('✖');
});

it('identifies critical failures', function () {
    expect(CheckStatus::FAIL->isCritical())->toBeTrue()
        ->and(CheckStatus::WARN->isCritical())->toBeFalse()
        ->and(CheckStatus::PASS->isCritical())->toBeFalse();
});
