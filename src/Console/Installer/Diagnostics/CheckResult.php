<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Diagnostics;

final readonly class CheckResult
{
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public string $message,
        public ?string $suggestedFix = null,
    ) {}

    public static function pass(string $name, string $message): self
    {
        return new self(
            name: $name,
            status: CheckStatus::PASS,
            message: $message,
            suggestedFix: null,
        );
    }

    public static function warn(string $name, string $message, ?string $suggestedFix = null): self
    {
        return new self(
            name: $name,
            status: CheckStatus::WARN,
            message: $message,
            suggestedFix: $suggestedFix,
        );
    }

    public static function fail(string $name, string $message, ?string $suggestedFix = null): self
    {
        return new self(
            name: $name,
            status: CheckStatus::FAIL,
            message: $message,
            suggestedFix: $suggestedFix,
        );
    }

    public function isPass(): bool
    {
        return $this->status === CheckStatus::PASS;
    }

    public function isWarn(): bool
    {
        return $this->status === CheckStatus::WARN;
    }

    public function isFail(): bool
    {
        return $this->status === CheckStatus::FAIL;
    }

    public function isCritical(): bool
    {
        return $this->status->isCritical();
    }
}
