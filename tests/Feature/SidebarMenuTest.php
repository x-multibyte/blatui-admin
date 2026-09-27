<?php

declare(strict_types=1);

use BlatUI\Admin\Layout\Menu as MenuBuilder;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Support\Facades\Blade;

test('sidebar menu renders hierarchical items with active states', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $menu = new MenuBuilder;
    $tree = $menu->toTree();

    expect($tree)->not->toBeEmpty();
    expect($tree[0]['title'])->toBe('Dashboard');
    expect($tree[1]['title'])->toBe('Admin');
    expect($tree[1]['children'])->toHaveCount(5);

    $html = Blade::render('<x-blatui-admin::partials.sidebar-menu :tree="$tree" />', ['tree' => $tree]);
    expect($html)->toContain('Dashboard')
        ->and($html)->toContain('Admin')
        ->and($html)->toContain('Users')
        ->and($html)->toContain('Roles');
});
