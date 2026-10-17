<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Diagnostics\CheckResult;
use BlatUI\Admin\Console\Installer\Diagnostics\CheckStatus;

it('instantiates via constructor with all properties', function () {
    $result = new CheckResult(
        name: 'Node.js Version',
        status: CheckStatus::PASS,
        message: 'v20.10.0 detected',
        suggestedFix: null,
    );

    expect($result->name)->toBe('Node.js Version')
        ->and($result->status)->toBe(CheckStatus::PASS)
        ->and($result->message)->toBe('v20.10.0 detected')
        ->and($result->suggestedFix)->toBeNull()
        ->and($result->isPass())->toBeTrue()
        ->and($result->isWarn())->toBeFalse()
        ->and($result->isFail())->toBeFalse()
        ->and($result->isCritical())->toBeFalse();
});

it('creates pass result via named constructor', function () {
    $result = CheckResult::pass('PHP Version', '8.4.1 detected');

    expect($result->name)->toBe('PHP Version')
        ->and($result->status)->toBe(CheckStatus::PASS)
        ->and($result->message)->toBe('8.4.1 detected')
        ->and($result->suggestedFix)->toBeNull()
        ->and($result->isPass())->toBeTrue();
});

it('creates warn result via named constructor with optional fix', function () {
    $result = CheckResult::warn(
        'Tailwind CSS',
        'Tailwind CSS v3 detected.',
        'Run "npx @tailwindcss/upgrade" to upgrade to Tailwind v4+.',
    );

    expect($result->name)->toBe('Tailwind CSS')
        ->and($result->status)->toBe(CheckStatus::WARN)
        ->and($result->message)->toBe('Tailwind CSS v3 detected.')
        ->and($result->suggestedFix)->toBe('Run "npx @tailwindcss/upgrade" to upgrade to Tailwind v4+.')
        ->and($result->isWarn())->toBeTrue()
        ->and($result->isCritical())->toBeFalse();
});

it('creates fail result via named constructor and marks as critical', function () {
    $result = CheckResult::fail(
        'Node.js',
        'Node.js is not installed.',
        'Install Node.js 18+ from https://nodejs.org.',
    );

    expect($result->name)->toBe('Node.js')
        ->and($result->status)->toBe(CheckStatus::FAIL)
        ->and($result->message)->toBe('Node.js is not installed.')
        ->and($result->suggestedFix)->toBe('Install Node.js 18+ from https://nodejs.org.')
        ->and($result->isFail())->toBeTrue()
        ->and($result->isCritical())->toBeTrue();
});
