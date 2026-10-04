<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources;

use BlatUI\Admin\Form;
use BlatUI\Admin\Form\Field\Multiselect;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Http\Controllers\Resources\Concerns\BuildsTreeOptions;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Illuminate\Http\JsonResponse;

class RolesController extends ResourceController
{
    use BuildsTreeOptions;

    protected string $title = 'Roles';

    protected function resourceKey(): string
    {
        return 'roles';
    }

    protected function model(): string
    {
        return Role::class;
    }

    protected function grid(): Grid
    {
        $grid = Grid::make(Role::class);

        $grid->column('name', 'Name');
        $grid->column('slug', 'Slug');
        $grid->column('permissions', 'Permissions')->display(fn (mixed $val, Role $row) => (string) $row->permissions()->count());
        $grid->column('created_at', 'Created At')->datetime();

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
        $form = Form::make(Role::class);

        $form->text('name', 'Name')->rules(['required', 'string', 'max:50']);

        $tableName = config('blatui-admin.database.roles_table');
        $form->text('slug', 'Slug')
            ->rules(['required', 'string', 'max:50', 'alpha_dash', 'unique:'.$tableName.',slug'.($key ? ','.$key : '')]);

        $permissionsField = new Multiselect('permissions', 'Permissions');
        $permissionsField->relation('permissions')->options($this->treeOptions(Permission::class, 'name'));
        $form->pushField($permissionsField);

        $menusField = new Multiselect('menus', 'Menus');
        $menusField->relation('menus')->options($this->treeOptions(Menu::class, 'title'));
        $form->pushField($menusField);

        if ($editing) {
            $record = Role::with(['permissions', 'menus'])->findOrFail($key);
            $form->fill([
                'name' => $record->name,
                'slug' => $record->slug,
                'permissions' => $record->permissions->pluck('id')->all(),
                'menus' => $record->menus->pluck('id')->all(),
            ]);
        }

        return $form;
    }

    protected function authorizeDestroy(mixed $record): ?JsonResponse
    {
        if ($record->slug === 'administrator') {
            abort(403, 'Cannot delete the administrator role.');
        }

        return null;
    }
}
