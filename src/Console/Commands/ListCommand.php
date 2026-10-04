<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use BlatUI\Admin\Console\Commands\Concerns\PrintsAdminInfo;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    use PrintsAdminInfo;

    /**
     * The command signature.
     */
    protected $signature = 'admin:list';

    /**
     * The command description.
     */
    protected $description = 'List all BlatUI Admin commands.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->printAdminInfo();

        return self::SUCCESS;
    }
}
