<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Wizard;

final readonly class InstallContext
{
    /**
     * @param  array<int, string>  $selectedComponents
     */
    public function __construct(
        public bool $dryRun = false,
        public bool $force = false,
        public bool $fresh = false,
        public bool $interactive = true,
        public ?string $composerPath = null,
        public bool $installApexCharts = false,
        public array $selectedComponents = [],
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromOptions(array $options): self
    {
        $dryRun = (bool) ($options['dry-run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $fresh = (bool) ($options['fresh'] ?? false);
        $interactive = ! ((bool) ($options['no-interaction'] ?? false));
        $composerPath = isset($options['composer-path']) && is_string($options['composer-path']) && $options['composer-path'] !== ''
            ? $options['composer-path']
            : null;

        return new self(
            dryRun: $dryRun,
            force: $force,
            fresh: $fresh,
            interactive: $interactive,
            composerPath: $composerPath,
        );
    }

    /**
     * @param  array<int, string>|null  $selectedComponents
     */
    public function withPreferences(
        ?bool $installApexCharts = null,
        ?array $selectedComponents = null,
    ): self {
        return new self(
            dryRun: $this->dryRun,
            force: $this->force,
            fresh: $this->fresh,
            interactive: $this->interactive,
            composerPath: $this->composerPath,
            installApexCharts: $installApexCharts ?? $this->installApexCharts,
            selectedComponents: $selectedComponents ?? $this->selectedComponents,
        );
    }
}
