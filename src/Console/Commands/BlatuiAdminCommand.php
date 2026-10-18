<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use BlatUI\Admin\Console\Installer\Wizard\InstallContext;
use BlatUI\Admin\Console\Installer\Wizard\InstallWizard;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\note;

#[AsCommand(name: 'blatui:admin')]
class BlatuiAdminCommand extends Command
{
    protected $signature = 'blatui:admin
                            {--install : Run the interactive installation wizard}
                            {--dry-run : Output the environment-aware installation plan without making changes}
                            {--fresh : Drop all tables and re-run migrations from scratch}
                            {--force : Force reinstallation and overwrite existing assets}
                            {--composer-path= : Path to the Composer binary}
                            {--install-apex-charts : Automatically install ApexCharts without prompting}
                            {--components=* : Specific BlatUI components to install}';

    protected $description = 'Manage BlatUI Admin lifecycle and dependencies.';

    public function handle(InstallWizard $wizard): int
    {
        if (! $this->option('install')) {
            note('BlatUI Admin lifecycle engine. Run "php artisan blatui:admin --install" to install.');

            return self::SUCCESS;
        }

        $context = InstallContext::fromOptions($this->options());

        return $wizard->run($context);
    }
}
