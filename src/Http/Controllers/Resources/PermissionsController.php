<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources;

use BlatUI\Admin\Form;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Http\Controllers\Resources\Concerns\BuildsTreeOptions;
use BlatUI\Admin\Models\Permission;

class PermissionsController extends ResourceController
{
    use BuildsTreeOptions;

    protected string $title = 'Permissions';

    protected function resourceKey(): string
    {
        return 'permissions';
    }

    protected function model(): string
    {
        return Permission::class;
    }

    protected function grid(): Grid
    {
        $grid = Grid::make(Permission::class);

        $grid->column('name', 'Name');
        $grid->column('slug', 'Slug');
        $grid->column('http_method', 'HTTP Method');
        $grid->column('http_path', 'HTTP Path')->limit(50);
        $grid->column('parent_id', 'Parent')->display(function (mixed $val, Permission $row) {
            if ($row->parent_id === 0) {
                return '—';
            }
            $parent = Permission::query()->find($row->parent_id);

            return $parent ? $parent->name : '—';
        });
        $grid->column('order', 'Order');

        $grid->filter(function (Filter $filter) {
            $filter->like('name', 'Name');
            $filter->like('slug', 'Slug');
        });

        $grid->actions(function (Row $actions) {
            $actions->edit();
            $actions->delete();
        });

        return $grid;
    }

    protected function form(bool $editing, ?int $key = null): Form
    {
        $form = Form::make(Permission::class);

        $form->text('name', 'Name')->rules(['required', 'string', 'max:50']);

        $tableName = config('blatui-admin.database.permissions_table');
        $form->text('slug', 'Slug')
            ->rules(['required', 'string', 'max:50', 'alpha_dash', 'unique:'.$tableName.',slug'.($key ? ','.$key : '')]);

        $form->text('http_method', 'HTTP Method')
            ->rules(['nullable', 'string', 'max:255'])
            ->help('Comma separated, e.g. GET,POST. Leave blank to match any method.');

        $form->textarea('http_path', 'HTTP Path')
            ->rules(['nullable', 'string'])
            ->help('One path per line. Leave blank to match any path.');

        $form->text('order', 'Order')->rules(['nullable', 'integer', 'min:0']);

        $form->select('parent_id', 'Parent')->options($this->parentOptions($key));

        if ($editing) {
            $record = Permission::findOrFail($key);
            $form->fill($record->toArray());
        }

        return $form;
    }

    /**
     * @return array<int, string>
     */
    protected function parentOptions(mixed $excludeId): array
    {
        $options = [0 => '— Top level —'] + $this->treeOptions(Permission::class, 'name');

        if ($excludeId !== null) {
            unset($options[(int) $excludeId]);
        }

        return $options;
    }
}
