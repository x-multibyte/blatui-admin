<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources;

use BlatUI\Admin\Form;
use BlatUI\Admin\Form\Field\Multiselect;
use BlatUI\Admin\Form\Field\Select;
use BlatUI\Admin\Form\Field\SwitchField;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Http\Controllers\Resources\Concerns\BuildsTreeOptions;
use BlatUI\Admin\Models\Menu;
use BlatUI\Admin\Models\Role;

class MenusController extends ResourceController
{
    use BuildsTreeOptions;

    protected string $title = 'Menu';

    protected function resourceKey(): string
    {
        return 'menu';
    }

    protected function model(): string
    {
        return Menu::class;
    }

    protected function grid(): Grid
    {
        $grid = Grid::make(Menu::class);

        $grid->column('title', 'Title');
        $grid->column('icon', 'Icon');
        $grid->column('uri', 'URI');
        $grid->column('order', 'Order');
        $grid->column('show', 'Show')
            ->using([1 => 'Yes', 0 => 'No'])
            ->badge([1 => 'success', 0 => 'secondary']);
        $grid->column('created_at', 'Created At')->datetime();

        $grid->filter(function (Filter $filter) {
            $filter->like('title', 'Title');
            $filter->like('uri', 'URI');
        });

        $grid->actions(function (Row $actions) {
            $actions->edit();
            $actions->delete();
        });

        return $grid;
    }

    protected function form(bool $editing, ?int $key = null): Form
    {
        $form = Form::make(Menu::class);

        $form->text('title', 'Title')->rules(['required', 'string', 'max:50']);
        $form->text('icon', 'Icon')
            ->help('A Lucide icon name, e.g. lucide-users.')
            ->rules(['nullable', 'string', 'max:50']);
        $form->text('uri', 'URI')
            ->help('Relative to the admin prefix, e.g. auth/users. Leave blank for a grouping item.')
            ->rules(['nullable', 'string', 'max:50']);
        $form->text('order', 'Order')->rules(['nullable', 'integer', 'min:0']);

        $form->pushField(new SwitchField('show', 'Show'));

        $parentField = new Select('parent_id', 'Parent');
        $parentField->options($this->parentOptions($key));
        $form->pushField($parentField);

        $rolesField = new Multiselect('roles', 'Roles');
        $rolesField->relation('roles')->options(Role::query()->orderBy('name')->pluck('name', 'id')->all());
        $form->pushField($rolesField);

        if ($editing) {
            $record = Menu::with(['roles'])->findOrFail($key);
            $form->fill([
                'title' => $record->title,
                'icon' => $record->icon,
                'uri' => $record->uri,
                'order' => $record->order,
                'show' => $record->show,
                'parent_id' => $record->parent_id,
                'roles' => $record->roles->pluck('id')->all(),
            ]);
        }

        return $form;
    }

    /**
     * @return array<int, string>
     */
    protected function parentOptions(mixed $excludeId): array
    {
        $options = $this->treeOptions(Menu::class, 'title');

        if ($excludeId !== null) {
            unset($options[(int) $excludeId]);
        }

        return [0 => '— Top level —'] + $options;
    }
}
