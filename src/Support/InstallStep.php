<?php

declare(strict_types=1);

namespace BlatUI\Admin\Support;

use BlatUI\Admin\Console\Commands\AdminInstallCommand;

/**
 * A single, environment-aware installation step.
 *
 * Each step knows how to check whether it still applies to the host app
 * (shouldRun), how to execute itself (run), and how to describe itself
 * for dry-run output (plan).
 */
final class InstallStep
{
    /**
     * @var (callable(AdminInstallCommand): bool)|null
     */
    private $shouldRun;

    /**
     * @var (callable(AdminInstallCommand): mixed)|null
     */
    private $run;

    /**
     * @var (callable(AdminInstallCommand): string)|null
     */
    private $plan;

    /**
     * @param  (callable(AdminInstallCommand): bool)|null  $shouldRun
     * @param  (callable(AdminInstallCommand): mixed)|null  $run
     * @param  (callable(AdminInstallCommand): string)|null  $plan
     */
    public function __construct(
        public readonly string $title,
        ?callable $shouldRun = null,
        ?callable $run = null,
        ?callable $plan = null,
    ) {
        $this->shouldRun = $shouldRun;
        $this->run = $run;
        $this->plan = $plan;
    }

    public function shouldRun(AdminInstallCommand $command): bool
    {
        return $this->shouldRun === null || ($this->shouldRun)($command) === true;
    }

    public function run(AdminInstallCommand $command): mixed
    {
        return $this->run === null ? null : ($this->run)($command);
    }

    public function plan(AdminInstallCommand $command): ?string
    {
        return $this->plan === null ? null : ($this->plan)($command);
    }
}
