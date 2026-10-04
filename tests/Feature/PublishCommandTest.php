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
    File::deleteDirectory(public_path('vendor/blatui-admin'));

    foreach (File::glob(database_path('migrations/*_create_admin_tables.php')) as $migration) {
        File::delete($migration);
    }
}

test('it publishes the config via non-interactive options', function () {
    $this->artisan('admin:publish', [
        '--config' => true,
        '--force' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('BlatUI Admin resources published successfully.');

    expect(File::exists(config_path('blatui-admin.php')))->toBeTrue();
});

test('it publishes all resources when using the --all option', function () {
    $this->artisan('admin:publish', [
        '--all' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeTrue();
    expect(File::exists(base_path('routes/admin.php')))->toBeTrue();
    expect(File::exists(database_path('seeders/AdminTablesSeeder.php')))->toBeTrue();
    expect(File::isDirectory(resource_path('views/vendor/blatui-admin')))->toBeTrue();
    expect(File::isDirectory(lang_path('vendor/blatui-admin')))->toBeTrue();
    expect(File::isDirectory(public_path('vendor/blatui-admin')))->toBeTrue();
    expect(File::glob(database_path('migrations/*_create_admin_tables.php')))->not->toBeEmpty();
});

test('it publishes the routes file and the seeders via their own flags', function () {
    $this->artisan('admin:publish', [
        '--routes' => true,
        '--seeders' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists(base_path('routes/admin.php')))->toBeTrue();
    expect(File::exists(database_path('seeders/AdminTablesSeeder.php')))->toBeTrue();
});

test('it prompts interactively when no options are passed', function () {
    $this->artisan('admin:publish')
        ->expectsQuestion('Which resources would you like to publish?', ['blatui-admin-config'])
        ->expectsConfirmation('Do you want to overwrite any existing files?', 'yes')
        ->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeTrue();
});

test('it handles an empty interactive selection gracefully', function () {
    $this->artisan('admin:publish')
        ->expectsQuestion('Which resources would you like to publish?', [])
        ->expectsOutputToContain('No resources selected to publish.')
        ->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeFalse();
});
