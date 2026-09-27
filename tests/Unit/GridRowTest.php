<?php

declare(strict_types=1);

use BlatUI\Admin\Grid\Actions\Delete;
use BlatUI\Admin\Grid\Actions\Edit;
use BlatUI\Admin\Grid\Actions\QuickEdit;
use BlatUI\Admin\Grid\Actions\Show;
use BlatUI\Admin\Grid\Column;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Grid\RowAction;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

test('grid row renders cells and actions', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $admin = Administrator::where('username', 'admin')->first();
    $row = new Row($admin, 0);

    expect($row->getKey())->toBe(1);
    expect($row->renderActions())->toContain('Edit', 'Delete');
});

test('grid row resolves primary key from model, array and object', function () {
    // Eloquent model
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
    $admin = Administrator::where('username', 'admin')->first();
    $modelRow = new Row($admin, 0);
    expect($modelRow->getKey())->toBe(1);

    // Array with id
    $arrayRow = new Row(['id' => 99, 'name' => 'John'], 1);
    expect($arrayRow->getKey())->toBe(99)
        ->and($arrayRow->getNumber())->toBe(1)
        ->and($arrayRow->getData())->toBe(['id' => 99, 'name' => 'John'])
        ->and($arrayRow->model())->toBe(['id' => 99, 'name' => 'John']);

    // Array with custom key name
    $customKeyRow = new Row(['uuid' => 'abc-123', 'name' => 'Custom']);
    $customKeyRow->setKeyName('uuid');
    expect($customKeyRow->getKey())->toBe('abc-123')
        ->and($customKeyRow->getKeyName())->toBe('uuid');

    // StdClass object
    $obj = new stdClass;
    $obj->id = 555;
    $obj->title = 'Test Object';
    $objRow = new Row($obj);
    expect($objRow->getKey())->toBe(555);

    // Null fallback
    $emptyRow = new Row('invalid data');
    expect($emptyRow->getKey())->toBeNull();
});

test('grid row renders column cell correctly', function () {
    $row = new Row(['id' => 1, 'status' => 'active', 'username' => 'administrator']);

    $col1 = new Column('username');
    expect($row->cell($col1))->toBeInstanceOf(HtmlString::class)
        ->toHtml()->toBe('administrator');

    $col2 = new Column('status');
    $col2->badge('success', ['active' => 'Active Status']);
    expect($row->cell($col2))->toBeInstanceOf(HtmlString::class)
        ->toHtml()->toContain('Active Status');
});

test('grid row renders checkbox with key and alpine attributes', function () {
    $row = new Row(['id' => 42, 'name' => 'Row 42'], 3);

    $checkbox = $row->renderCheckbox();
    expect($checkbox)->toContain('name="_row_id[]"')
        ->and($checkbox)->toContain('value="42"')
        ->and($checkbox)->toContain('data-id="42"')
        ->and($checkbox)->toContain('x-model="selectedRows"')
        ->and($checkbox)->toContain('type="checkbox"');

    $customCheckbox = $row->renderCheckbox('custom_batch_ids');
    expect($customCheckbox)->toContain('name="custom_batch_ids[]"');
});

test('grid row manages action disabling individually and globally', function () {
    $row = new Row(['id' => 10], 0, 'admin/posts');

    // Default actions: Show, Edit, Delete (QuickEdit disabled by default)
    $actionsHtml = $row->renderActions();
    expect($actionsHtml)->toContain('Show')
        ->and($actionsHtml)->toContain('Edit')
        ->and($actionsHtml)->toContain('Delete')
        ->and($actionsHtml)->not->toContain('Quick Edit');

    // Disable Show
    $row->disableShow();
    expect($row->renderActions())->not->toContain('Show')
        ->and($row->renderActions())->toContain('Edit')
        ->and($row->renderActions())->toContain('Delete');

    // Disable Edit
    $row->disableEdit();
    expect($row->renderActions())->not->toContain('Edit');

    // Disable Delete
    $row->disableDelete();
    expect($row->renderActions())->not->toContain('Delete');

    // Enable QuickEdit
    $row->quickEdit(true);
    expect($row->renderActions())->toContain('Quick Edit');

    // Disable all actions globally
    $row->disableActions();
    expect($row->renderActions())->toBe('');
    expect($row->isActionsDisabled())->toBeTrue();
});

test('grid row actions closure can configure actions using fluent syntax', function () {
    $row = new Row(['id' => 5], 0, 'admin/users');

    $row->actions(function ($actions) {
        $actions->disableDelete();
        $actions->disableShow();
        $actions->quickEdit();
    });

    $html = $row->renderActions();
    expect($html)->toContain('Edit')
        ->and($html)->toContain('Quick Edit')
        ->and($html)->not->toContain('Delete')
        ->and($html)->not->toContain('Show');
});

test('grid row supports prepending, appending and custom row actions', function () {
    $row = new Row(['id' => 7], 0, 'admin/articles');

    $customAction = new class extends RowAction
    {
        public function render(?Row $row = null): string
        {
            if ($row !== null) {
                $this->setRow($row);
            }

            return '<button class="custom-action">Custom #'.$this->getKey().'</button>';
        }
    };

    $row->prepend('<span class="prepended">Start</span>');
    $row->append('<span class="appended">End</span>');
    $row->addAction($customAction);

    $html = $row->renderActions();
    expect($html)->toContain('<span class="prepended">Start</span>')
        ->and($html)->toContain('<span class="appended">End</span>')
        ->and($html)->toContain('Custom #7');
});

test('show action renders detail link with resource and key', function () {
    $row = new Row(['id' => 12], 0, '/admin/posts');
    $show = new Show;
    $show->setRow($row);

    expect($show->getTitle())->toBe('Show')
        ->and($show->getUrl())->toBe('/admin/posts/12');

    $html = $show->render();
    expect($html)->toContain('href="/admin/posts/12"')
        ->and($html)->toContain('Show')
        ->and($html)->toContain('<svg');

    // Custom title and explicit url
    $customShow = new Show('Inspect');
    $customShow->setRow($row)->setUrl('/custom/detail/12');
    expect($customShow->getTitle())->toBe('Inspect')
        ->and($customShow->getUrl())->toBe('/custom/detail/12')
        ->and($customShow->render())->toContain('href="/custom/detail/12"')
        ->and($customShow->render())->toContain('Inspect');
});

test('edit action renders edit link with resource and key', function () {
    $row = new Row(['id' => 15], 0, '/admin/products');
    $edit = new Edit;
    $edit->setRow($row);

    expect($edit->getTitle())->toBe('Edit')
        ->and($edit->getUrl())->toBe('/admin/products/15/edit');

    $html = $edit->render();
    expect($html)->toContain('href="/admin/products/15/edit"')
        ->and($html)->toContain('Edit')
        ->and($html)->toContain('<svg');
});

test('quick edit action renders trigger button with alpine dispatch', function () {
    $row = new Row(['id' => 20], 0, '/admin/orders');
    $quickEdit = new QuickEdit('Fast Edit');
    $quickEdit->setRow($row);

    $html = $quickEdit->render();
    expect($html)->toContain('Fast Edit')
        ->and($html)->toContain('data-key="20"')
        ->and($html)->toContain('data-url="/admin/orders/20/edit"')
        ->and($html)->toContain('$dispatch(\'quick-edit\', { key: \'20\', url: \'/admin/orders/20/edit\' })');
});

test('delete action renders alpine confirmation popover and ajax fetch delete', function () {
    $row = new Row(['id' => 33], 0, '/admin/tags');
    $delete = new Delete;
    $delete->setRow($row);

    expect($delete->getTitle())->toBe('Delete')
        ->and($delete->getUrl())->toBe('/admin/tags/33');

    $html = $delete->render();

    // Alpine component state
    expect($html)->toContain('x-data="{')
        ->and($html)->toContain('confirming: false')
        ->and($html)->toContain('loading: false');

    // Confirmation popover
    expect($html)->toContain('x-show="confirming"')
        ->and($html)->toContain('Cancel')
        ->and($html)->toContain('Confirm')
        ->and($html)->toContain('Are you sure you want to delete this record?');

    // AJAX fetch with DELETE method and headers
    expect($html)->toContain("fetch('/admin/tags/33'")
        ->and($html)->toContain("method: 'DELETE'")
        ->and($html)->toContain("'X-Requested-With': 'XMLHttpRequest'")
        ->and($html)->toContain("'X-CSRF-TOKEN': token");

    // Sonner toast custom event dispatch
    expect($html)->toContain("window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Deleted successfully', type: 'success' } }))");

    // Row removal or reload
    expect($html)->toContain('$el.closest(\'tr\')')
        ->and($html)->toContain('tr.remove()')
        ->and($html)->toContain('window.location.reload()');
});

test('row provides arrayable, property accessor and model helpers', function () {
    $data = ['id' => 88, 'username' => 'alice', 'email' => 'alice@example.com'];
    $row = new Row($data, 5);

    expect($row->column('username'))->toBe('alice')
        ->and($row->username)->toBe('alice')
        ->and($row->email)->toBe('alice@example.com')
        ->and($row->toArray())->toBe($data)
        ->and($row->getNumber())->toBe(5);

    $row->setNumber(6);
    expect($row->getNumber())->toBe(6);

    $row->setData(['id' => 89]);
    expect($row->getKey())->toBe(89);
});

test('grid row implements Htmlable, Renderable, and Stringable contracts', function () {
    $row = new Row(['id' => 1, 'username' => 'administrator'], 0);

    expect($row)->toBeInstanceOf(Htmlable::class)
        ->and($row->toHtml())->toBe($row->render())
        ->and((string) $row)->toBe($row->toHtml());
});
