<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    cleanUpPublishedResources();
});

afterEach(function () {
    cleanUpPublishedResources();
});

function cleanUpPublishedResources(): void
{
    File::delete(config_path('blatui-admin.php'));
    File::delete(base_path('routes/admin.php'));
    File::delete(database_path('seeders/AdminTablesSeeder.php'));
    File::deleteDirectory(resource_path('views/vendor/blatui-admin'));
    File::deleteDirectory(lang_path('vendor/blatui-admin'));

    foreach (File::glob(database_path('migrations/*_create_admin_tables.php')) as $migration) {
        File::delete($migration);
    }
}

function packageSourcePath(string $relative): string
{
    return dirname(__DIR__, 2).'/'.$relative;
}

function relativeShippedFiles(string $root): array
{
    $files = [];

    foreach (File::allFiles($root) as $file) {
        $files[] = ltrim(str_replace($root, '', $file->getPathname()), '/');
    }

    sort($files);

    return $files;
}

test('TC-1 it publishes the routes file byte for byte', function () {
    $this->artisan('admin:publish', [
        '--routes' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(File::get(base_path('routes/admin.php')))
        ->toBe(File::get(packageSourcePath('routes/blatui-admin.php')));
});

test('TC-2 it publishes the seeder file byte for byte', function () {
    $this->artisan('admin:publish', [
        '--seeders' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(File::get(database_path('seeders/AdminTablesSeeder.php')))
        ->toBe(File::get(packageSourcePath('database/seeders/AdminTablesSeeder.php')));
});

test('TC-3 it publishes every view file with the shipped directory structure', function () {
    $this->artisan('admin:publish', [
        '--views' => true,
        '--force' => true,
    ])->assertSuccessful();

    $source = relativeShippedFiles(packageSourcePath('resources/views'));
    $published = relativeShippedFiles(resource_path('views/vendor/blatui-admin'));

    expect($source)->not->toBeEmpty();
    expect($published)->toBe($source);
});

test('TC-5 it refuses to overwrite existing files without --force', function () {
    File::ensureDirectoryExists(config_path());
    File::put(config_path('blatui-admin.php'), '<?php return ["custom" => true];');

    $this->artisan('admin:publish', ['--config' => true])
        ->expectsConfirmation('Do you want to overwrite any existing files?', 'no')
        ->assertSuccessful();

    expect(File::get(config_path('blatui-admin.php')))
        ->toBe('<?php return ["custom" => true];');
});

test('it publishes the config byte for byte when --force is given', function () {
    $this->artisan('admin:publish', [
        '--config' => true,
        '--force' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('BlatUI Admin resources published successfully.');

    expect(File::get(config_path('blatui-admin.php')))
        ->toBe(File::get(packageSourcePath('config/blatui-admin.php')));
});

test('it publishes all resources when using the --all option', function () {
    $this->artisan('admin:publish', [
        '--all' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(File::get(config_path('blatui-admin.php')))
        ->toBe(File::get(packageSourcePath('config/blatui-admin.php')));
    expect(File::get(base_path('routes/admin.php')))
        ->toBe(File::get(packageSourcePath('routes/blatui-admin.php')));
    expect(File::get(database_path('seeders/AdminTablesSeeder.php')))
        ->toBe(File::get(packageSourcePath('database/seeders/AdminTablesSeeder.php')));
    expect(File::isDirectory(resource_path('views/vendor/blatui-admin')))->toBeTrue();
    expect(File::isDirectory(lang_path('vendor/blatui-admin')))->toBeTrue();
    expect(File::glob(database_path('migrations/*_create_admin_tables.php')))->not->toBeEmpty();
});

test('it prompts interactively when no options are passed', function () {
    $this->artisan('admin:publish')
        ->expectsQuestion('Which resources would you like to publish?', ['blatui-admin-config'])
        ->expectsConfirmation('Do you want to overwrite any existing files?', 'yes')
        ->assertSuccessful();

    expect(File::get(config_path('blatui-admin.php')))
        ->toBe(File::get(packageSourcePath('config/blatui-admin.php')));
});

test('it handles an empty interactive selection gracefully', function () {
    $this->artisan('admin:publish')
        ->expectsQuestion('Which resources would you like to publish?', [])
        ->expectsOutputToContain('No resources selected to publish.')
        ->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeFalse();
});
