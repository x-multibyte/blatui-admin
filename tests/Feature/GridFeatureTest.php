<?php

declare(strict_types=1);

use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Column;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Model;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Grid\Tools;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

test('grid compiles complete table with data, columns and tools', function () {
    Administrator::where('username', 'admin')->update(['name' => 'Super Administrator']);

    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID')->sortable();
        $grid->column('username', 'Username')->badge();
        $grid->column('name', 'Name');
        $grid->column('created_at', 'Created At')->datetime();
    });

    $html = $grid->render();

    expect($html)->toContain('ID')
        ->and($html)->toContain('Username')
        ->and($html)->toContain('admin')
        ->and($html)->toContain('Super Administrator');
});

test('grid implements Renderable, Htmlable and Responsable interfaces', function () {
    $grid = new Grid(new Administrator);

    expect($grid)->toBeInstanceOf(Renderable::class)
        ->and($grid)->toBeInstanceOf(Htmlable::class)
        ->and($grid)->toBeInstanceOf(Responsable::class)
        ->and($grid->toHtml())->toBe($grid->render())
        ->and((string) $grid)->toBe($grid->render());
});

test('grid registers columns and retrieves columns collection', function () {
    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID')->sortable();
        $grid->column('username', 'User Account');
        $grid->column('email', 'Email Address')->help('User contact email');
    });

    $columns = $grid->getColumns();

    expect($columns)->toHaveCount(3)
        ->and($columns->has('id'))->toBeTrue()
        ->and($columns->has('username'))->toBeTrue()
        ->and($columns->has('email'))->toBeTrue()
        ->and($columns->get('id'))->toBeInstanceOf(Column::class)
        ->and($columns->get('id')->getLabel())->toBe('ID')
        ->and($columns->get('id')->isSortable())->toBeTrue()
        ->and($columns->get('email')->getHelp())->toBe('User contact email');
});

test('grid provides access to underlying model, filter and tools instances', function () {
    $grid = new Grid(new Administrator);

    expect($grid->model())->toBeInstanceOf(Model::class)
        ->and($grid->filter())->toBeInstanceOf(Filter::class)
        ->and($grid->tools())->toBeInstanceOf(Tools::class);

    // Test filter callback
    $grid->filter(function (Filter $filter) {
        $filter->equal('id', 'Filter ID');
    });
    expect($grid->filter()->getField('id'))->not->toBeNull();

    // Test tools callback
    $grid->tools(function (Tools $tools) {
        $tools->createButtonText('New Administrator');
    });
    expect($grid->tools()->renderCreateButton())->toContain('New Administrator');
});

test('grid builds rows collection and sets row attributes and keys', function () {
    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID');
        $grid->column('username', 'Username');
    });

    $rows = $grid->rows();

    expect($rows)->toHaveCount(1)
        ->and($rows->first())->toBeInstanceOf(Row::class)
        ->and($rows->first()->getKey())->toBe(1)
        ->and($rows->first()->column('username'))->toBe('admin');
});

test('grid supports custom actions callback and global actions disabling', function () {
    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID');
        $grid->actions(function (Row $actions) {
            $actions->disableDelete();
            $actions->quickEdit();
        });
    });

    $row = $grid->rows()->first();
    $actionsHtml = View::make('blatui-admin::grid.partials.actions', ['row' => $row])->render();

    expect($actionsHtml)->toContain('Edit')
        ->and($actionsHtml)->toContain('Quick Edit')
        ->and($actionsHtml)->not->toContain('Delete');

    // Global actions disable
    $disabledGrid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID');
        $grid->disableActions();
    });

    expect($disabledGrid->isActionsDisabled())->toBeTrue();
    $renderedHtml = $disabledGrid->render();
    expect($renderedHtml)->not->toContain('>Action</th>');
});

test('grid forwards control disable flags to tools and filter', function () {
    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->disableCreateButton();
        $grid->disableRefreshButton();
        $grid->disableFilterButton();
        $grid->disableBatchActions();
        $grid->disableFilter();
    });

    expect($grid->tools()->isCreateButtonEnabled())->toBeFalse()
        ->and($grid->tools()->isRefreshButtonEnabled())->toBeFalse()
        ->and($grid->tools()->isFilterButtonEnabled())->toBeFalse()
        ->and($grid->tools()->isBatchActionsEnabled())->toBeFalse()
        ->and($grid->isFilterDisabled())->toBeTrue();

    $html = $grid->render();
    expect($html)->not->toContain('Reload')
        ->and($html)->not->toContain('Batch Actions');
});

test('grid handles pagination configuration and disabling', function () {
    // Pagination enabled by default
    $grid = Grid::make(new Administrator);
    $grid->paginate(10);
    expect($grid->model()->isPaginated())->toBeTrue()
        ->and($grid->model()->getPerPage())->toBe(10);

    // Disable pagination
    $grid->disablePagination();
    expect($grid->model()->isPaginated())->toBeFalse();

    // Toggle pagination with bool
    $grid->paginate(false);
    expect($grid->model()->isPaginated())->toBeFalse();

    $grid->paginate(true);
    expect($grid->model()->isPaginated())->toBeTrue();
});

test('grid supports resource, title, and description configuration', function () {
    $grid = new Grid(new Administrator);

    // Default resource derived from model table
    expect($grid->resource())->toBe('/admin/admin_users');

    // Custom resource
    $grid->resource('/admin/custom-users');
    expect($grid->resource())->toBe('/admin/custom-users')
        ->and($grid->tools()->getResource())->toBe('/admin/custom-users');

    // Fluent setResource
    $grid->setResource('/admin/fluent-users');
    expect($grid->resource())->toBe('/admin/fluent-users');

    // Title & description getters and setters
    $grid->title('User Directory');
    expect($grid->title())->toBe('User Directory')
        ->and($grid->getTitle())->toBe('User Directory');

    $grid->setTitle('Updated Directory');
    expect($grid->getTitle())->toBe('Updated Directory');

    $grid->description('Manage all registered administrator accounts');
    expect($grid->description())->toBe('Manage all registered administrator accounts')
        ->and($grid->getDescription())->toBe('Manage all registered administrator accounts');

    $grid->setDescription('Updated Description');
    expect($grid->getDescription())->toBe('Updated Description');
});

test('grid implements Responsable and wraps in Content layout response', function () {
    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->title('Administrator Management');
        $grid->description('System administration panel');
        $grid->column('id', 'ID');
        $grid->column('username', 'Username');
    });

    $request = Request::create('/admin/admin_users', 'GET');
    $response = $grid->toResponse($request);

    expect($response)->toBeInstanceOf(SymfonyResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    $content = (string) $response->getContent();
    expect($content)->toContain('Administrator Management')
        ->and($content)->toContain('System administration panel')
        ->and($content)->toContain('admin');
});

test('grid renders empty state when dataset has no records', function () {
    Administrator::query()->delete();

    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID');
        $grid->column('username', 'Username');
    });

    $html = $grid->render();

    expect($html)->toContain('No records found')
        ->and($html)->toContain('Total')
        ->and($html)->toContain('0');
});

test('grid renders sortable column links with active sort chevrons', function () {
    $request = Request::create('/admin/admin_users', 'GET', [
        '_sort' => [
            'column' => 'username',
            'type' => 'asc',
        ],
    ]);
    app()->instance('request', $request);

    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID')->sortable();
        $grid->column('username', 'Username')->sortable();
    });

    expect($grid->getSortDirection('username'))->toBe('asc')
        ->and($grid->getSortDirection('id'))->toBeNull();

    // Ascending sort toggles to desc in next link
    $sortUrl = $grid->getSortUrl('username');
    expect($sortUrl)->toContain('_sort%5Bcolumn%5D=username')
        ->and($sortUrl)->toContain('_sort%5Btype%5D=desc');

    $html = $grid->render();
    expect($html)->toContain('Username');
});

test('grid renders batch actions and Alpine.js checkbox bindings', function () {
    $grid = Grid::make(new Administrator, function (Grid $grid) {
        $grid->column('id', 'ID');
        $grid->column('username', 'Username');
    });

    $html = $grid->render();

    expect($html)->toContain('x-data="{')
        ->and($html)->toContain('selectedRows:')
        ->and($html)->toContain('toggleAll()')
        ->and($html)->toContain('Batch Actions')
        ->and($html)->toContain('name="_row_id[]"')
        ->and($html)->toContain('x-model="selectedRows"');
});
