<?php

declare(strict_types=1);

use BlatUI\Admin\Contracts\Repository;
use BlatUI\Admin\Grid\Model as GridModel;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Repositories\EloquentRepository;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

test('grid model manages queries, sorting and pagination', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $gridModel = new GridModel(new Administrator);
    $gridModel->setPerPage(10);
    $gridModel->orderBy('id', 'desc');

    $records = $gridModel->buildData();

    expect($records)->toHaveCount(1)
        ->and($records->first()->username)->toBe('admin');
});

test('grid model accepts model, builder, class string, or repository', function () {
    $fromModel = new GridModel(new Administrator);
    expect($fromModel->repository())->toBeInstanceOf(Repository::class)
        ->and($fromModel->repository())->toBeInstanceOf(EloquentRepository::class);

    $fromBuilder = new GridModel(Administrator::query());
    expect($fromBuilder->repository())->toBeInstanceOf(EloquentRepository::class);

    $fromString = new GridModel(Administrator::class);
    expect($fromString->repository())->toBeInstanceOf(EloquentRepository::class);

    $customRepo = new EloquentRepository(new Administrator);
    $fromRepo = new GridModel($customRepo);
    expect($fromRepo->repository())->toBe($customRepo);
});

test('grid model manages per page and page name configuration', function () {
    $gridModel = new GridModel(new Administrator);

    expect($gridModel->getPerPage())->toBe(20)
        ->and($gridModel->getPageName())->toBe('page')
        ->and($gridModel->getPerPageName())->toBe('per_page');

    $gridModel->setPerPage(15)
        ->setPageName('p')
        ->setPerPageName('per-page');

    expect($gridModel->getPerPage())->toBe(15)
        ->and($gridModel->getPageName())->toBe('p')
        ->and($gridModel->getPerPageName())->toBe('per-page');
});

test('grid model supports eager loading relations', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $gridModel = new GridModel(new Administrator);
    $gridModel->with('roles');

    $records = $gridModel->buildData();
    expect($records->first()->relationLoaded('roles'))->toBeTrue();

    $gridModelArray = new GridModel(new Administrator);
    $gridModelArray->with(['roles', 'permissions']);
    $recordsArray = $gridModelArray->buildData();
    expect($recordsArray->first()->relationLoaded('roles'))->toBeTrue()
        ->and($recordsArray->first()->relationLoaded('permissions'))->toBeTrue();
});

test('grid model applies where conditions', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $gridModel = new GridModel(new Administrator);
    $gridModel->where('username', 'admin');
    $records = $gridModel->buildData();
    expect($records)->toHaveCount(1);

    $gridModelNotFound = new GridModel(new Administrator);
    $gridModelNotFound->where('username', 'nonexistent_user');
    $recordsNotFound = $gridModelNotFound->buildData();
    expect($recordsNotFound)->toHaveCount(0);
});

test('grid model supports disabling pagination', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $gridModel = new GridModel(new Administrator);
    expect($gridModel->isPaginated())->toBeTrue();

    $gridModel->paginate(false);
    expect($gridModel->isPaginated())->toBeFalse();

    $records = $gridModel->buildData();
    expect($records)->toBeInstanceOf(Collection::class)
        ->and($records)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($records)->toHaveCount(1);

    $gridModel->paginate(true);
    expect($gridModel->isPaginated())->toBeTrue();
    $paginatedRecords = $gridModel->buildData();
    expect($paginatedRecords)->toBeInstanceOf(LengthAwarePaginator::class);
});

test('grid model sorts via request query parameters', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    Administrator::create([
        'username' => 'alice',
        'password' => bcrypt('password'),
        'name' => 'Alice',
    ]);
    Administrator::create([
        'username' => 'zoe',
        'password' => bcrypt('password'),
        'name' => 'Zoe',
    ]);

    // Format 1: _sort[username]=asc
    app()->instance('request', Request::create('/admin/users', 'GET', [
        '_sort' => ['username' => 'asc'],
    ]));

    $gridModel = new GridModel(new Administrator);
    $gridModel->orderBy('id', 'desc'); // Programmatic order should be overridden by request sort
    $records = $gridModel->buildData();
    expect($records->first()->username)->toBe('admin'); // admin, alice, zoe: 'admin' comes first alphabetically

    // Format 1 with desc: _sort[username]=desc
    app()->instance('request', Request::create('/admin/users', 'GET', [
        '_sort' => ['username' => 'desc'],
    ]));

    $gridModelDesc = new GridModel(new Administrator);
    $recordsDesc = $gridModelDesc->buildData();
    expect($recordsDesc->first()->username)->toBe('zoe');

    // Format 2: _sort[column]=username&_sort[type]=asc
    app()->instance('request', Request::create('/admin/users', 'GET', [
        '_sort' => ['column' => 'name', 'type' => 'asc'],
    ]));

    $gridModelCol = new GridModel(new Administrator);
    $recordsCol = $gridModelCol->buildData();
    expect($recordsCol->first()->name)->toBe('Administrator');

    // Format 3: _sort=-username
    app()->instance('request', Request::create('/admin/users', 'GET', [
        '_sort' => '-username',
    ]));

    $gridModelStr = new GridModel(new Administrator);
    $recordsStr = $gridModelStr->buildData();
    expect($recordsStr->first()->username)->toBe('zoe');
});

test('grid model provides model metadata and key name', function () {
    $gridModel = new GridModel(new Administrator);

    expect($gridModel->getKeyName())->toBe('id')
        ->and($gridModel->getModel())->toBeInstanceOf(Administrator::class);
});

test('grid model respects request per_page parameter', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    app()->instance('request', Request::create('/admin/users', 'GET', [
        'per_page' => '5',
    ]));

    $gridModel = new GridModel(new Administrator);
    $records = $gridModel->buildData();

    expect($records)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($records->perPage())->toBe(5);
});

test('grid model integrates with filter handler and query callbacks', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $gridModel = new GridModel(new Administrator);

    // Callable filter
    $gridModel->setFilter(function ($query) {
        $query->where('username', 'admin');
    });
    expect($gridModel->getFilter())->not->toBeNull();

    $records = $gridModel->buildData();
    expect($records)->toHaveCount(1);

    // Object filter with apply
    $filterObj = new class
    {
        public function apply($query): void
        {
            $query->where('username', 'non_existing');
        }
    };

    $gridModel->setFilter($filterObj);
    $emptyRecords = $gridModel->buildData();
    expect($emptyRecords)->toHaveCount(0);

    // Custom query callback
    $gridModelCallback = new GridModel(new Administrator);
    $gridModelCallback->addQueryCallback(function ($query) {
        $query->where('id', '>', 0);
    });
    expect($gridModelCallback->buildData())->toHaveCount(1);
});
