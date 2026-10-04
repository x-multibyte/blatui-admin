<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands\Concerns;

use BlatUI\Admin\Admin;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * @mixin Command
 */
trait PrintsAdminInfo
{
    /**
     * Print the ASCII banner, version, and admin command list.
     */
    protected function printAdminInfo(): void
    {
        $this->line('');
        $this->line('   <fg=cyan>____   __        __   __  __ ____</>');
        $this->line('  <fg=cyan>/ __ ) / /____ _ / /_ / / / //  _/</>');
        $this->line(' <fg=cyan>/ __  |/ // __ `// __// / / / / /  </>');
        $this->line('<fg=cyan>/ /_/ // // /_/ // /_ / /_/ /_/ /   </>');
        $this->line('<fg=cyan>/_____//_/ \__,_/ \__/ \____//___/  </>');
        $this->line('');
        $this->line('  <fg=gray>BlatUI Admin</> <fg=green>v'.Admin::version().'</>');
        $this->line('');

        $this->printCommandList();
    }

    /**
     * Discover and print all registered admin:* commands.
     */
    protected function printCommandList(): void
    {
        $app = $this->getApplication();

        if ($app === null) {
            return;
        }

        /** @var SymfonyCommand[] $commands */
        $commands = array_filter(
            $app->all('admin'),
            fn (SymfonyCommand $cmd): bool => $cmd->getName() !== 'admin',
        );

        if (empty($commands)) {
            return;
        }

        // Sort by name for a stable, predictable output.
        ksort($commands);

        $this->line('  <fg=yellow>Available Commands:</>');
        $this->line('');

        // Calculate column width from the longest command name.
        $maxLen = max(array_map(
            fn (SymfonyCommand $cmd): int => strlen((string) $cmd->getName()),
            $commands,
        ));

        foreach ($commands as $command) {
            $name = str_pad((string) $command->getName(), $maxLen + 2);
            $this->line(sprintf(
                '  <fg=green>%s</> <fg=gray>%s</>',
                $name,
                $command->getDescription(),
            ));
        }

        $this->line('');
    }
}
