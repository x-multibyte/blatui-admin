<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('it cancels the uninstall when the confirmation is declined', function () {
    File::put(config_path('blatui-admin.php'), '<?php return [];');

    $this->artisan('admin:uninstall')
        ->expectsConfirmation('Are you sure you want to uninstall BlatUI Admin?', 'no')
        ->expectsOutputToContain('Uninstall cancelled.')
        ->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeTrue();
});

test('TC-4 it deletes published resources including routes and seeders symmetrically', function () {
    File::put(config_path('blatui-admin.php'), '<?php return [];');
    File::put(base_path('routes/admin.php'), '<?php ');
    File::ensureDirectoryExists(database_path('seeders'));
    File::put(database_path('seeders/AdminTablesSeeder.php'), '<?php ');

    File::ensureDirectoryExists(resource_path('views/vendor/blatui-admin'));
    File::ensureDirectoryExists(lang_path('vendor/blatui-admin'));

    $this->artisan('admin:uninstall')
        ->expectsConfirmation('Are you sure you want to uninstall BlatUI Admin?', 'yes')
        ->expectsOutputToContain('Uninstalling BlatUI Admin...')
        ->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeFalse();
    expect(File::exists(base_path('routes/admin.php')))->toBeFalse();
    expect(File::exists(database_path('seeders/AdminTablesSeeder.php')))->toBeFalse();
    expect(File::isDirectory(resource_path('views/vendor/blatui-admin')))->toBeFalse();
    expect(File::isDirectory(lang_path('vendor/blatui-admin')))->toBeFalse();
});

test('it deletes published migrations after rolling them back', function () {
    File::ensureDirectoryExists(database_path('migrations'));
    File::put(
        database_path('migrations/2026_01_01_000000_create_admin_tables.php'),
        '<?php ',
    );

    $this->artisan('admin:uninstall', ['--force' => true])
        ->expectsOutputToContain('Deleted migration: '.database_path('migrations/2026_01_01_000000_create_admin_tables.php'))
        ->assertSuccessful();

    expect(File::exists(database_path('migrations/2026_01_01_000000_create_admin_tables.php')))->toBeFalse();
});

test('it uninstalls without prompting when the --force option is used', function () {
    File::put(config_path('blatui-admin.php'), '<?php return [];');

    $this->artisan('admin:uninstall', ['--force' => true])
        ->expectsOutputToContain('Uninstalling BlatUI Admin...')
        ->doesntExpectOutputToContain('Are you sure you want to uninstall BlatUI Admin?')
        ->assertSuccessful();

    expect(File::exists(config_path('blatui-admin.php')))->toBeFalse();
});
