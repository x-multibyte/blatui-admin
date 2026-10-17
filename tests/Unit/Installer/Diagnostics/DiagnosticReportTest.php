<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Diagnostics\CheckResult;
use BlatUI\Admin\Console\Installer\Diagnostics\DiagnosticReport;

it('starts empty and allows adding check results', function () {
    $report = new DiagnosticReport;

    expect($report)->toBeEmpty()
        ->and($report->count())->toBe(0)
        ->and($report->hasCriticalFailures())->toBeFalse()
        ->and($report->hasWarnings())->toBeFalse();

    $report->add(CheckResult::pass('PHP', '8.4 detected'));

    expect($report)->toHaveCount(1)
        ->and($report->passCount())->toBe(1)
        ->and($report->warnCount())->toBe(0)
        ->and($report->failCount())->toBe(0);
});

it('aggregates counts and filters critical failures and warnings', function () {
    $report = new DiagnosticReport([
        CheckResult::pass('PHP', '8.4 detected'),
        CheckResult::warn('Tailwind', 'v3 detected', 'Upgrade to v4'),
        CheckResult::fail('Node', 'Node not found', 'Install Node 18+'),
    ]);

    expect($report->count())->toBe(3)
        ->and($report->passCount())->toBe(1)
        ->and($report->warnCount())->toBe(1)
        ->and($report->failCount())->toBe(1)
        ->and($report->hasCriticalFailures())->toBeTrue()
        ->and($report->hasWarnings())->toBeTrue()
        ->and($report->failures())->toHaveCount(1)
        ->and($report->failures()[0]->name)->toBe('Node')
        ->and($report->warnings())->toHaveCount(1)
        ->and($report->warnings()[0]->name)->toBe('Tailwind');
});

it('formats table rows for laravel/prompts table display', function () {
    $report = new DiagnosticReport([
        CheckResult::pass('PHP', '8.4 detected'),
        CheckResult::warn('Tailwind', 'v3 detected', 'Run upgrade'),
    ]);

    $rows = $report->toTableRows();

    expect($rows)->toBeArray()
        ->and($rows)->toHaveCount(2)
        ->and($rows[0][0])->toContain('PASS')
        ->and($rows[0][1])->toBe('PHP')
        ->and($rows[0][2])->toBe('8.4 detected')
        ->and($rows[1][0])->toContain('WARN')
        ->and($rows[1][1])->toBe('Tailwind')
        ->and($rows[1][2])->toContain('Run upgrade');
});
