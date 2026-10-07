<?php

declare(strict_types=1);
use BlatUI\Admin\Http\Controllers\Resources\AdministratorsController;
use BlatUI\Admin\Http\Controllers\Resources\MenusController;
use BlatUI\Admin\Http\Controllers\Resources\OperationLogController;
use BlatUI\Admin\Http\Controllers\Resources\PermissionsController;
use BlatUI\Admin\Http\Controllers\Resources\RolesController;

test('all resource routes are registered under the configured prefix', function () {
    foreach (['users', 'roles', 'permissions', 'menu', 'logs'] as $key) {
        expect(parse_url(route("admin.{$key}.index"), PHP_URL_PATH))->toBe("/admin/auth/{$key}")
            ->and(parse_url(route("admin.{$key}.create"), PHP_URL_PATH))->toBe("/admin/auth/{$key}/create")
            ->and(parse_url(route("admin.{$key}.store"), PHP_URL_PATH))->toBe("/admin/auth/{$key}")
            ->and(parse_url(route("admin.{$key}.edit", ['id' => 1]), PHP_URL_PATH))->toBe("/admin/auth/{$key}/1/edit")
            ->and(parse_url(route("admin.{$key}.update", ['id' => 1]), PHP_URL_PATH))->toBe("/admin/auth/{$key}/1")
            ->and(parse_url(route("admin.{$key}.destroy", ['id' => 1]), PHP_URL_PATH))->toBe("/admin/auth/{$key}/1")
            ->and(parse_url(route("admin.{$key}.batch-destroy"), PHP_URL_PATH))->toBe("/admin/auth/{$key}/batch-delete");
    }
});

test('batch-delete is not shadowed by the id parameter', function () {
    app('router')->getRoutes()->refreshNameLookups();

    $route = collect(app('router')->getRoutes())->first(
        fn ($route) => $route->uri() === 'admin/auth/users/batch-delete'
            && in_array('DELETE', $route->methods(), true),
    );

    expect($route)->not->toBeNull()
        ->and($route->hasParameter('id'))->toBeFalse();

    // The Symfony form carries the compiled regex, which admits the literal
    // `batch-delete` and nothing else.
    expect($route->toSymfonyRoute()->compile()->getRegex())->toContain('batch\-delete');
});

test('the id routes only match numeric ids', function () {
    $route = collect(app('router')->getRoutes())->first(
        fn ($route) => $route->uri() === 'admin/auth/users/{id}'
            && in_array('DELETE', $route->methods(), true),
    );

    expect($route)->not->toBeNull()
        ->and($route->wheres['id'] ?? null)->toBe('[0-9]+');
});

test('guests are redirected away from every resource index', function () {
    foreach (['users', 'roles', 'permissions', 'menu', 'logs'] as $key) {
        $this->get("/admin/auth/{$key}")->assertRedirect('/admin/auth/login');
    }
});

test('the built-in controllers are wired from config', function () {
    expect(config('blatui-admin.resources.administrators'))->toBe(AdministratorsController::class)
        ->and(config('blatui-admin.resources.roles'))->toBe(RolesController::class)
        ->and(config('blatui-admin.resources.permissions'))->toBe(PermissionsController::class)
        ->and(config('blatui-admin.resources.menus'))->toBe(MenusController::class)
        ->and(config('blatui-admin.resources.logs'))->toBe(OperationLogController::class);
});
