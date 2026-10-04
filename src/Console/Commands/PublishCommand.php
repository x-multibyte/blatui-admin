<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:publish
                            {--config : Publish config file}
                            {--migrations : Publish migration files}
                            {--routes : Publish the admin routes file}
                            {--seeders : Publish the database seeders}
                            {--views : Publish views}
                            {--lang : Publish language files}
                            {--assets : Publish assets}
                            {--a|all : Publish all resources}
                            {--f|force : Overwrite any existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish BlatUI Admin registered resources (config, routes, seeders, migrations, views, lang, assets)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');

        $tags = [];

        if ($this->option('all')) {
            $tags = ['blatui-admin'];
        } else {
            $optionsMap = [
                'config' => 'blatui-admin-config',
                'migrations' => 'blatui-admin-migrations',
                'routes' => 'blatui-admin-routes',
                'seeders' => 'blatui-admin-seeders',
                'views' => 'blatui-admin-views',
                'lang' => 'blatui-admin-lang',
                'assets' => 'blatui-admin-assets',
            ];

            foreach ($optionsMap as $option => $tag) {
                if ($this->option($option)) {
                    $tags[] = $tag;
                }
            }

            // Interactive mode if no options are specified
            if (empty($tags)) {
                $choices = multiselect(
                    label: 'Which resources would you like to publish?',
                    options: [
                        'blatui-admin-config' => 'Config',
                        'blatui-admin-migrations' => 'Migrations',
                        'blatui-admin-routes' => 'Routes',
                        'blatui-admin-seeders' => 'Seeders',
                        'blatui-admin-views' => 'Views',
                        'blatui-admin-lang' => 'Lang',
                        'blatui-admin-assets' => 'Assets',
                    ],
                );

                if (empty($choices)) {
                    $this->info('No resources selected to publish.');

                    return self::SUCCESS;
                }

                $tags = $choices;
            }
        }

        if (! $force) {
            $force = confirm(
                label: 'Do you want to overwrite any existing files?',
                default: false,
            );
        }

        foreach ($tags as $tag) {
            $this->call('vendor:publish', [
                '--tag' => $tag,
                '--force' => $force,
            ]);
        }

        $this->info('BlatUI Admin resources published successfully.');

        return self::SUCCESS;
    }
}
