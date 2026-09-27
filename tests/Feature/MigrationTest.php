<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('it runs admin migrations and creates all 7 tables', function () {
    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('admin_users'))->toBeTrue()
        ->and(Schema::hasTable('admin_roles'))->toBeTrue()
        ->and(Schema::hasTable('admin_permissions'))->toBeTrue()
        ->and(Schema::hasTable('admin_menu'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_users'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_permissions'))->toBeTrue()
        ->and(Schema::hasTable('admin_role_menu'))->toBeTrue()
        ->and(Schema::hasTable('admin_operation_log'))->toBeTrue();
});
