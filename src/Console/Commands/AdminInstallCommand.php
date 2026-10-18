<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use BlatUI\Admin\Console\Installer\Wizard\InstallContext;
use BlatUI\Admin\Console\Installer\Wizard\InstallWizard;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'admin:install')]
class AdminInstallCommand extends Command
{
    protected $signature = 'admin:install
                            {--dry-run : Show the environment-aware installation plan without changing anything}
                            {--fresh : Drop all tables and re-run the migrations from scratch}
                            {--force : Re-run steps even when they appear to be already installed}
                            {--composer-path= : Path to the Composer binary, when it is not on PATH}
                            {--install-apex-charts : Automatically install ApexCharts without prompting}
                            {--components=* : Specific BlatUI components to install}';

    protected $description = 'Install the BlatUI Admin panel and dependencies (alias for blatui:admin --install).';

    public function handle(InstallWizard $wizard): int
    {
        $context = InstallContext::fromOptions($this->options());

        return $wizard->run($context);
    }
}
