<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;

class UninstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:uninstall {--f|force : Force uninstall without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uninstall the BlatUI Admin package and rollback migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            warning('This will delete all blatui-admin published resources and rollback its database migrations. Application files under App\Admin\ will be kept. THIS CANNOT BE UNDONE.');

            $confirmed = confirm(
                label: 'Are you sure you want to uninstall BlatUI Admin?',
                default: false,
            );

            if (! $confirmed) {
                $this->info('Uninstall cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Uninstalling BlatUI Admin...');

        // 1. Rollback migrations
        $this->info('Rolling back migrations...');

        try {
            Artisan::call('migrate:rollback', [
                '--path' => 'vendor/x-multibyte/blatui-admin/database/migrations',
                '--force' => true,
            ]);
            $this->line(Artisan::output());
        } catch (Exception $e) {
            $this->error('Failed to rollback migrations: '.$e->getMessage());
        }

        // 2. Clean published files
        $this->info('Cleaning up published resources...');

        $filesToDelete = [
            config_path('blatui-admin.php'),
            base_path('routes/admin.php'),
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->line("Deleted: {$file}");
            }
        }

        $directoriesToDelete = [
            resource_path('views/vendor/blatui-admin'),
            lang_path('vendor/blatui-admin'),
            public_path('vendor/blatui-admin'),
        ];

        foreach ($directoriesToDelete as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
                $this->line("Deleted directory: {$dir}");
            }
        }

        // 3. Clean published migration files
        $migrationFiles = File::glob(database_path('migrations/*_create_admin_tables.php'));

        foreach ($migrationFiles as $migrationFile) {
            File::delete($migrationFile);
            $this->line("Deleted migration: {$migrationFile}");
        }

        $this->info('BlatUI Admin uninstalled successfully.');

        return self::SUCCESS;
    }
}
