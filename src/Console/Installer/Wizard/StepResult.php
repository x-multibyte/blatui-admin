<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Wizard;

final readonly class StepResult
{
    public function __construct(
        public string $title,
        public bool $successful,
        public ?string $message = null,
        public ?string $plan = null,
        public bool $skipped = false,
        public ?string $skipReason = null,
    ) {}

    public static function success(string $title, ?string $message = null): self
    {
        return new self(
            title: $title,
            successful: true,
            message: $message,
        );
    }

    public static function failure(string $title, string $message): self
    {
        return new self(
            title: $title,
            successful: false,
            message: $message,
        );
    }

    public static function skip(string $title, string $reason): self
    {
        return new self(
            title: $title,
            successful: true,
            skipped: true,
            skipReason: $reason,
        );
    }

    public static function plan(string $title, string $plan): self
    {
        return new self(
            title: $title,
            successful: true,
            plan: $plan,
        );
    }
}
