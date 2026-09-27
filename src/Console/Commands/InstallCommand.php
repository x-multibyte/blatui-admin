<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use Database\Seeders\AdminTablesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the BlatUI Admin package and initialize database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing BlatUI Admin...');

        // 1. Run migrations
        $this->call('migrate');

        // 2. Run seeders if not already seeded
        $userModel = config('blatui-admin.database.users_model');

        if ($userModel && class_exists($userModel) && $userModel::count() === 0) {
            $this->call('db:seed', ['--class' => AdminTablesSeeder::class]);
        }

        // 3. Publish config and routes if needed
        $this->callSilent('vendor:publish', ['--tag' => 'blatui-admin-config']);
        $this->callSilent('vendor:publish', ['--tag' => 'blatui-admin-routes']);

        // 4. Create upload storage directory
        $disk = config('blatui-admin.upload.disk', 'public');
        $uploadDir = storage_path('app/public/admin/images');

        if (! File::isDirectory($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true, true);
        }

        $this->info('BlatUI Admin installed successfully.');
        $this->line('<comment>Default Administrator:</comment>');
        $this->line('Username: <info>admin</info>');
        $this->line('Password: <info>admin</info>');

        return self::SUCCESS;
    }
}
