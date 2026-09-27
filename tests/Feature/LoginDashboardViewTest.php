<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('login page renders blatui card and fields', function () {
    $response = $this->get('/admin/auth/login');
    $response->assertStatus(200);
    $response->assertSee('BlatUI Admin');
    $response->assertSee('Remember me');
});

test('dashboard renders content with stat metric cards', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    $response->assertStatus(200);
    $response->assertSee('Overview');
    $response->assertSee('Total Administrators');
});
