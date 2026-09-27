<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use Illuminate\Console\Command;

class AdminCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'blatui-admin:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package blatui-admin.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Admin placeholder command executed.');

        return self::SUCCESS;
    }
}
