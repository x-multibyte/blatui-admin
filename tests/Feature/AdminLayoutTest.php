<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;

test('authenticated admin can access dashboard with master layout', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    $response->assertStatus(200);
    $response->assertSee('BlatUI Admin');
    $response->assertSee('Dashboard');
    $response->assertSee('Administrator');
});
