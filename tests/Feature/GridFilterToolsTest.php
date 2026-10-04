<?php

declare(strict_types=1);

use BlatUI\Admin\Grid\BatchAction;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Filter\Equal;
use BlatUI\Admin\Grid\Filter\Like;
use BlatUI\Admin\Grid\Model;
use BlatUI\Admin\Grid\Tools;
use BlatUI\Admin\Grid\Tools\BatchDelete;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

test('grid filter builds conditions and fields correctly', function () {
    $filter = new Filter(new Administrator);

    $idField = $filter->equal('id', 'ID');
    $nameField = $filter->like('username', 'Username');
    $gtField = $filter->gt('id', 'ID Greater');
    $gteField = $filter->gte('id', 'ID Greater Equal');
    $ltField = $filter->lt('id', 'ID Less');
    $lteField = $filter->lte('id', 'ID Less Equal');
    $betweenField = $filter->between('created_at', 'Created At');
    $inField = $filter->in('id', 'IDs')->options([1 => 'Admin', 2 => 'Editor']);
    $whereField = $filter->where(function (Builder $query, mixed $value): void {
        $query->where('name', $value);
    }, 'Custom Where');

    expect($filter->getFields())->toHaveCount(9)
        ->and($filter->getField('id'))->toBe($idField)
        ->and($filter->getField('username'))->toBe($nameField)
        ->and($idField)->toBeInstanceOf(Equal::class)
        ->and($nameField)->toBeInstanceOf(Like::class)
        ->and($idField->getColumn())->toBe('id')
        ->and($idField->getLabel())->toBe('ID')
        ->and($idField->getName())->toBe('id');

    $filter->removeField('username');
    expect($filter->getFields())->toHaveCount(8)
        ->and($filter->getField('username'))->toBeNull();
});

test('grid filter applies conditions to query based on request parameters', function () {
    $request = Request::create('/admin/auth/users', 'GET', [
        'username' => 'adm',
        'id' => 1,
    ]);
    app()->instance('request', $request);

    $filter = new Filter(new Administrator);
    $filter->equal('id', 'ID');
    $filter->like('username', 'Username');

    $query = Administrator::query();
    $filter->execute($query);

    $results = $query->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()?->username)->toBe('admin');
});

test('grid filter does not alter query when request has empty parameters', function () {
    $request = Request::create('/admin/auth/users', 'GET', [
        'username' => '',
        'id' => null,
    ]);
    app()->instance('request', $request);

    $filter = new Filter(new Administrator);
    $filter->equal('id', 'ID');
    $filter->like('username', 'Username');

    $query = Administrator::query();
    $filter->apply($query);

    $results = $query->get();
    expect($results)->toHaveCount(1);
});

test('grid filter applies gt gte lt lte between and in conditions', function () {
    // Test between
    $request = Request::create('/admin/auth/users', 'GET', [
        'id_between' => ['start' => 1, 'end' => 10],
    ]);
    app()->instance('request', $request);

    $filter = new Filter(new Administrator);
    $between = $filter->between('id', 'ID Range');
    $between->name('id_between');

    $query = Administrator::query();
    $filter->execute($query);
    expect($query->count())->toBe(1);

    // Test in filter with array
    $inRequest = Request::create('/admin/auth/users', 'GET', [
        'status_ids' => [1, 2, 3],
    ]);
    app()->instance('request', $inRequest);

    $inFilter = new Filter(new Administrator);
    $inField = $inFilter->in('id', 'ID In');
    $inField->name('status_ids');

    $inQuery = Administrator::query();
    $inFilter->execute($inQuery);
    expect($inQuery->count())->toBe(1);

    // Test gt condition that excludes record
    $gtRequest = Request::create('/admin/auth/users', 'GET', [
        'min_id' => 10,
    ]);
    app()->instance('request', $gtRequest);

    $gtFilter = new Filter(new Administrator);
    $gtField = $gtFilter->gt('id', 'Min ID');
    $gtField->name('min_id');

    $gtQuery = Administrator::query();
    $gtFilter->execute($gtQuery);
    expect($gtQuery->count())->toBe(0);
});

test('grid filter handles relation dot notation conditions', function () {
    $request = Request::create('/admin/auth/users', 'GET', [
        'roles.slug' => 'administrator',
    ]);
    app()->instance('request', $request);

    $filter = new Filter(new Administrator);
    $filter->equal('roles.slug', 'Role Slug');

    $query = Administrator::query();
    $filter->execute($query);

    expect($query->count())->toBe(1);

    // Mismatched role
    $missRequest = Request::create('/admin/auth/users', 'GET', [
        'roles.slug' => 'non-existent',
    ]);
    app()->instance('request', $missRequest);

    $missFilter = new Filter(new Administrator);
    $missFilter->equal('roles.slug', 'Role Slug');

    $missQuery = Administrator::query();
    $missFilter->execute($missQuery);
    expect($missQuery->count())->toBe(0);
});

test('grid filter resetUrl removes filter parameters and page while preserving sorting', function () {
    $request = Request::create('/admin/auth/users', 'GET', [
        'username' => 'admin',
        'id' => 1,
        'page' => 2,
        '_sort' => [
            'column' => 'id',
            'type' => 'desc',
        ],
    ]);
    app()->instance('request', $request);

    $filter = new Filter(new Administrator);
    $filter->equal('id', 'ID');
    $filter->like('username', 'Username');

    $resetUrl = $filter->resetUrl();

    expect($resetUrl)->toContain('_sort%5Bcolumn%5D=id')
        ->and($resetUrl)->toContain('_sort%5Btype%5D=desc')
        ->and($resetUrl)->not->toContain('username')
        ->and($resetUrl)->not->toContain('page');
});

test('grid filter tracks expansion state and active filters', function () {
    $filter = new Filter(new Administrator);
    $filter->equal('id', 'ID');

    // Without request input, not expanded by default
    expect($filter->hasActiveFilters())->toBeFalse()
        ->and($filter->isExpanded())->toBeFalse();

    // Explicitly expanded
    $filter->expand();
    expect($filter->isExpanded())->toBeTrue();

    // Explicitly collapsed
    $filter->collapse();
    expect($filter->isExpanded())->toBeFalse();

    // With active request input, auto-expanded
    $request = Request::create('/admin/auth/users', 'GET', ['id' => 1]);
    app()->instance('request', $request);

    $activeFilter = new Filter(new Administrator);
    $activeFilter->equal('id', 'ID');

    expect($activeFilter->hasActiveFilters())->toBeTrue()
        ->and($activeFilter->isExpanded())->toBeTrue();
});

test('grid filter renders form with search, reset and hidden sorting inputs', function () {
    $request = Request::create('/admin/auth/users', 'GET', [
        '_sort' => [
            'column' => 'username',
            'type' => 'asc',
        ],
    ]);
    app()->instance('request', $request);

    $filter = new Filter(new Administrator);
    $filter->equal('id', 'ID');
    $filter->like('username', 'Username');

    $html = $filter->render();

    expect($html)->toContain('<form')
        ->and($html)->toContain('name="id"')
        ->and($html)->toContain('name="username"')
        ->and($html)->toContain('Search')
        ->and($html)->toContain('Reset')
        ->and($html)->toContain('name="_sort[column]"')
        ->and($html)->toContain('value="username"')
        ->and($html)->toContain('@toggle-grid-filter.window')
        ->and($filter->toHtml())->toBe($html)
        ->and((string) $filter)->toBe($html);
});

test('grid tools manages create, refresh and filter buttons', function () {
    $tools = new Tools;
    $tools->resource('/admin/auth/users');

    expect($tools->isCreateButtonEnabled())->toBeTrue()
        ->and($tools->isRefreshButtonEnabled())->toBeTrue()
        ->and($tools->isFilterButtonEnabled())->toBeTrue()
        ->and($tools->isBatchActionsEnabled())->toBeTrue()
        ->and($tools->getCreateUrl())->toBe('/admin/auth/users/create');

    // Custom create url
    $tools->createUrl('/custom/create');
    expect($tools->getCreateUrl())->toBe('/custom/create');

    // Disable buttons
    $tools->disableCreateButton();
    $tools->disableRefreshButton();
    $tools->disableFilterButton();

    expect($tools->isCreateButtonEnabled())->toBeFalse()
        ->and($tools->isRefreshButtonEnabled())->toBeFalse()
        ->and($tools->isFilterButtonEnabled())->toBeFalse();

    $html = $tools->render();
    expect($html)->not->toContain('/custom/create')
        ->not->toContain('Reload')
        ->not->toContain('Filter');

    // Re-enable buttons
    $tools->enableCreateButton();
    $tools->enableRefreshButton();
    $tools->enableFilterButton();

    $renderedHtml = $tools->render();
    expect($renderedHtml)->toContain('/custom/create')
        ->and($renderedHtml)->toContain('Reload')
        ->and($renderedHtml)->toContain('Filter')
        ->and($renderedHtml)->toContain('$dispatch(\'toggle-grid-filter\')');
});

test('grid tools supports batch actions and batch delete', function () {
    $tools = new Tools;
    $tools->resource('/admin/auth/users');

    expect($tools->getBatchActions())->toHaveKey(BatchDelete::class);

    $html = $tools->render();
    expect($html)->toContain('Batch Actions')
        ->and($html)->toContain('selectedRows.length > 0')
        ->and($html)->toContain('/admin/auth/users/batch-delete');

    // Disable batch delete via batch closure
    $tools->batch(function (Tools $batch) {
        $batch->disableDelete();
    });

    expect($tools->getBatchActions())->not->toHaveKey(BatchDelete::class);

    // Add custom batch action
    $customAction = new class('Export Selected') extends BatchAction
    {
        public function render(): string
        {
            return '<button type="button">Export</button>';
        }
    };

    $tools->batch(function (Tools $batch) use ($customAction) {
        $batch->add($customAction);
    });

    expect($tools->render())->toContain('Export');

    // Disable all batch actions
    $tools->disableBatchActions();
    expect($tools->render())->not->toContain('Batch Actions');
});

test('grid tools supports custom prepended and appended elements', function () {
    $tools = new Tools;
    $tools->prepend('<div id="custom-left">Left Tool</div>');
    $tools->append('<div id="custom-right">Right Tool</div>');

    $html = $tools->render();
    expect($html)->toContain('id="custom-left"')
        ->and($html)->toContain('Left Tool')
        ->and($html)->toContain('id="custom-right"')
        ->and($html)->toContain('Right Tool');
});

test('batch delete action renders Fetch API delete and Sonner toast dispatch', function () {
    $batchDelete = new BatchDelete;
    $batchDelete->resource('/admin/auth/users')
        ->confirmText('Delete selected rows?')
        ->successMessage('Batch deleted successfully')
        ->noSelectedText('No records selected');

    $html = $batchDelete->render();

    expect($html)->toContain('/admin/auth/users/batch-delete')
        ->and($html)->toContain('Delete selected rows?')
        ->and($html)->toContain('Batch deleted successfully')
        ->and($html)->toContain('No records selected')
        ->and($html)->toContain('X-CSRF-TOKEN')
        ->and($html)->toContain('toast')
        ->and($html)->toContain('ids: selectedRows')
        ->and($batchDelete->toHtml())->toBe($html)
        ->and((string) $batchDelete)->toBe($html);
});

test('between filter supports one-sided bounds and in filter supports comma string', function () {
    // Between with only start
    $requestStart = Request::create('/admin/auth/users', 'GET', [
        'id_range' => ['start' => 1],
    ]);
    app()->instance('request', $requestStart);

    $filter = new Filter(new Administrator);
    $between = $filter->between('id', 'ID Range')->name('id_range');

    $query = Administrator::query();
    $filter->execute($query);
    expect($query->count())->toBe(1);

    // Between with only end
    $requestEnd = Request::create('/admin/auth/users', 'GET', [
        'id_range' => ['end' => 0],
    ]);
    app()->instance('request', $requestEnd);

    $queryEnd = Administrator::query();
    $filter->execute($queryEnd);
    expect($queryEnd->count())->toBe(0);

    // In filter with comma-separated string
    $inRequest = Request::create('/admin/auth/users', 'GET', [
        'id_list' => '1, 2, 3',
    ]);
    app()->instance('request', $inRequest);

    $inFilter = new Filter(new Administrator);
    $inField = $inFilter->in('id', 'ID List')->name('id_list');

    $inQuery = Administrator::query();
    $inFilter->execute($inQuery);
    expect($inQuery->count())->toBe(1);

    // Where closure filter
    $whereRequest = Request::create('/admin/auth/users', 'GET', [
        'custom_user' => 'admin',
    ]);
    app()->instance('request', $whereRequest);

    $whereFilter = new Filter(new Administrator);
    $whereFilter->where(function (Builder $q, mixed $val): void {
        $q->where('username', '=', $val);
    }, 'Custom Filter', 'custom_user');

    $whereQuery = Administrator::query();
    $whereFilter->execute($whereQuery);
    expect($whereQuery->count())->toBe(1);
});

test('grid filter integrates seamlessly with Grid Model coordinator', function () {
    $request = Request::create('/admin/auth/users', 'GET', [
        'username' => 'admin',
    ]);
    app()->instance('request', $request);

    $gridModel = new Model(new Administrator);
    $filter = new Filter(new Administrator);
    $filter->like('username', 'Username');

    $gridModel->setFilter($filter);
    $data = $gridModel->buildData();

    expect($data)->toHaveCount(1)
        ->and($data->first()?->username)->toBe('admin');

    // Mismatched filter excludes record
    $mismatchRequest = Request::create('/admin/auth/users', 'GET', [
        'username' => 'does-not-exist',
    ]);
    app()->instance('request', $mismatchRequest);

    $mismatchData = $gridModel->buildData();
    expect($mismatchData)->toHaveCount(0);
});

test('filter renders empty string when no fields are registered', function () {
    $filter = new Filter(new Administrator);
    expect($filter->render())->toBe('');
});

test('grid filter and tools implement Htmlable contract', function () {
    $filter = new Filter(new Administrator);
    $tools = new Tools;

    expect($filter)->toBeInstanceOf(Htmlable::class)
        ->and($tools)->toBeInstanceOf(Htmlable::class)
        ->and($filter->toHtml())->toBe($filter->render())
        ->and($tools->toHtml())->toBe($tools->render());
});

test('tools reload and filter buttons render without markup drift', function () {
    $tools = new Tools;

    // Byte-for-byte assertions: these buttons are static markup, so extracting
    // them to a Blade view must not alter a single character of output. The
    // closing tag carries no trailing newline — PHP 7.3+ nowdoc semantics put
    // the newline before the closing marker, not after it.
    $reload = $tools->renderRefreshButton();
    $filter = $tools->renderFilterButton();

    expect($reload)->toStartWith('<button type="button" @click="window.location.reload()"')
        ->and($reload)->toEndWith('</button>')
        ->and($reload)->toContain('<span>Reload</span>')
        ->and($filter)->toStartWith('<button type="button" @click="$dispatch(\'toggle-grid-filter\')"')
        ->and($filter)->toEndWith('</button>')
        ->and($filter)->toContain('<span>Filter</span>');
});

test('reload and filter buttons stay empty when disabled', function () {
    $tools = new Tools;

    $tools->disableRefreshButton()->disableFilterButton();

    expect($tools->renderRefreshButton())->toBe('')
        ->and($tools->renderFilterButton())->toBe('');
});
