<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Feature\Grid;

use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;
use RuntimeException;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

test('Grid::resolving registers container hook executed before instance is returned', function () {
    $called = false;
    Grid::resolving(function (Grid $grid) use (&$called) {
        $called = true;
        $grid->title('Injected Title');
    });

    $grid = Grid::make(Administrator::class);

    expect($called)->toBeTrue()
        ->and($grid->getTitle())->toBe('Injected Title');
});

test('Grid::resolved registers container hook executed after instance is resolved', function () {
    $called = false;
    Grid::resolved(function (Grid $grid) use (&$called) {
        $called = true;
    });

    $grid = Grid::make(Administrator::class);

    expect($called)->toBeTrue();
});

test('native container resolving hook also intercepts Grid::make', function () {
    $called = false;
    app()->resolving(Grid::class, function (Grid $grid) use (&$called) {
        $called = true;
        $grid->description('Container Description');
    });

    $grid = Grid::make(Administrator::class);

    expect($called)->toBeTrue()
        ->and($grid->getDescription())->toBe('Container Description');
});

test('Grid::make executes callback passed to it', function () {
    $called = false;
    $grid = Grid::make(Administrator::class, function (Grid $grid) use (&$called) {
        $called = true;
        $grid->column('username', 'Username');
    });

    expect($called)->toBeTrue()
        ->and($grid->getColumns()->has('username'))->toBeTrue();
});

test('Grid::resolving and resolved pass container as second parameter', function () {
    $resolvingContainer = null;
    $resolvedContainer = null;

    Grid::resolving(function (Grid $grid, $app) use (&$resolvingContainer) {
        $resolvingContainer = $app;
    });

    Grid::resolved(function (Grid $grid, $app) use (&$resolvedContainer) {
        $resolvedContainer = $app;
    });

    Grid::make(Administrator::class);

    expect($resolvingContainer)->toBe(app())
        ->and($resolvedContainer)->toBe(app());
});

test('Grid allows empty repository and can set model later or throw informative exception', function () {
    $grid = Grid::make();

    expect(fn () => $grid->model())
        ->toThrow(RuntimeException::class, 'Grid model is not initialized.')
        ->and(fn () => $grid->rows())
        ->toThrow(RuntimeException::class, 'Grid model is not initialized.');

    $model = $grid->model(Administrator::class);

    expect($grid->model())->toBe($model)
        ->and($grid->filter())->toBeInstanceOf(Filter::class);
});

test('Grid resolving hook can configure pagination and filter before model is attached without crashing', function () {
    Grid::resolving(function (Grid $grid) {
        $grid->paginate(15);
        $grid->disableFilter();
    });

    $grid = Grid::make();
    expect($grid->isFilterDisabled())->toBeTrue();

    // Attach model later
    $grid->model(Administrator::class);

    expect($grid->isFilterDisabled())->toBeTrue();
});

test('Grid preserves configured filters when model is attached after resolving hook', function () {
    Grid::resolving(function (Grid $grid) {
        $grid->filter(function (Filter $filter) {
            $filter->equal('status', 'Status');
        });
    });

    $grid = Grid::make();
    expect($grid->filter()->getFields())->toHaveCount(1);

    // Attach model later
    $grid->model(Administrator::class);

    expect($grid->filter()->getFields())->toHaveCount(1)
        ->and($grid->filter()->getModel())->not->toBeNull();
});
