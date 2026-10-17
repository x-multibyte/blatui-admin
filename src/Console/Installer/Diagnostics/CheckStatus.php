<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Diagnostics;

enum CheckStatus
{
    case PASS;
    case WARN;
    case FAIL;

    public function label(): string
    {
        return match ($this) {
            self::PASS => 'PASS',
            self::WARN => 'WARN',
            self::FAIL => 'FAIL',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PASS => '✔',
            self::WARN => '⚠',
            self::FAIL => '✖',
        };
    }

    public function isCritical(): bool
    {
        return $this === self::FAIL;
    }
}
