<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use BlatUI\Admin\Console\Concerns\HasBanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;


class AdminCommand extends Command
{
    use HasBanner;

    protected $signature = 'admin';

    protected $description = 'Admin panel with BlatUI.';

    public function handle(): int
    {
        $this->banner();

        return self::SUCCESS;
    }
}