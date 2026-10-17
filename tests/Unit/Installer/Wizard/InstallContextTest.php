<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Wizard\InstallContext;

it('creates default context with sensible defaults', function () {
    $context = new InstallContext;

    expect($context->dryRun)->toBeFalse()
        ->and($context->force)->toBeFalse()
        ->and($context->fresh)->toBeFalse()
        ->and($context->interactive)->toBeTrue()
        ->and($context->composerPath)->toBeNull()
        ->and($context->installApexCharts)->toBeFalse()
        ->and($context->selectedComponents)->toBe([]);
});

it('creates context from command options array', function () {
    $context = InstallContext::fromOptions([
        'dry-run' => true,
        'force' => true,
        'fresh' => false,
        'no-interaction' => true,
        'composer-path' => '/custom/bin/composer',
        'install-apex-charts' => true,
        'components' => ['button', 'card'],
    ]);

    expect($context->dryRun)->toBeTrue()
        ->and($context->force)->toBeTrue()
        ->and($context->fresh)->toBeFalse()
        ->and($context->interactive)->toBeFalse()
        ->and($context->composerPath)->toBe('/custom/bin/composer')
        ->and($context->installApexCharts)->toBeTrue()
        ->and($context->selectedComponents)->toBe(['button', 'card']);
});

it('creates derived context with user preferences', function () {
    $context = new InstallContext(dryRun: true);

    $updated = $context->withPreferences(
        installApexCharts: true,
        selectedComponents: ['button', 'card', 'table'],
    );

    expect($context->installApexCharts)->toBeFalse()
        ->and($context->selectedComponents)->toBe([])
        ->and($updated->dryRun)->toBeTrue()
        ->and($updated->installApexCharts)->toBeTrue()
        ->and($updated->selectedComponents)->toBe(['button', 'card', 'table']);
});
