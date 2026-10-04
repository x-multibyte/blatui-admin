<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Form;
use BlatUI\Admin\Form\Field\Multiselect;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class AdministratorsController extends ResourceController
{
    protected string $title = 'Administrators';

    protected string $description = 'Manage administrator accounts and their role assignments';

    protected function model(): string
    {
        return Administrator::class;
    }

    protected function resourceKey(): string
    {
        return 'users';
    }

    protected function grid(): Grid
    {
        return Grid::make(Administrator::class, function (Grid $grid): void {
            $grid->resource($this->resource());
            $grid->model()->with(['roles']);

            $grid->column('name', 'Name')->display(function (mixed $value, mixed $row = null): HtmlString {
                $nameStr = $value !== null && (string) $value !== '' ? (string) $value : ($row instanceof Administrator ? $row->username : '');
                $name = htmlspecialchars($nameStr, ENT_QUOTES, 'UTF-8');

                if ($row instanceof Administrator) {
                    $avatar = htmlspecialchars($row->getAvatarUrl(), ENT_QUOTES, 'UTF-8');

                    return new HtmlString(sprintf(
                        '<div class="flex items-center gap-2"><img src="%s" class="w-7 h-7 rounded-full object-cover inline-block" alt="%s"><span>%s</span></div>',
                        $avatar,
                        $name,
                        $name,
                    ));
                }

                return new HtmlString($name);
            });

            $grid->column('username', 'Username');

            $grid->column('roles', 'Roles')
                ->display(fn (mixed $value, mixed $row = null): string => ($row instanceof Administrator ? $row->roles : ($value instanceof Collection ? $value : collect()))->pluck('name')->implode(', '))
                ->badge();

            $grid->column('created_at', 'Created At')->datetime();

            $grid->filter(function (Filter $filter): void {
                $filter->like('username');
                $filter->like('name');
                $filter->between('created_at');
            });

            $grid->actions(function (Row $actions): void {
                $actions->disableShow();
                $actions->disableQuickEdit();
            });
        });
    }

    protected function form(bool $editing, ?int $id = null): Form
    {
        return Form::make(Administrator::class, function (Form $form) use ($editing, $id): void {
            $form->action($this->resource());
            $form->redirect($this->resource());

            $routeId = request()->route('id');
            $recordId = $id ?? $form->getKey() ?? (is_numeric($routeId) ? (int) $routeId : null);

            $table = (string) config('blatui-admin.database.users_table', 'admin_users');
            $uniqueRule = 'unique:'.$table.',username';

            if ($editing && $recordId !== null) {
                $uniqueRule .= ','.$recordId;
            }

            $form->text('username', 'Username')
                ->rules(['required', 'string', 'max:120', $uniqueRule, 'alpha_dash']);

            $form->text('name', 'Name')
                ->rules(['required', 'string', 'max:255']);

            $form->text('password', 'Password')
                ->rules($editing ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'])
                ->help($editing ? 'Leave blank to keep the current password.' : 'At least 6 characters.');

            $rolesField = (new Multiselect('roles', 'Roles'))
                ->relation('roles')
                ->options(Role::query()->orderBy('name')->pluck('name', 'id')->all());

            if ($editing && $recordId !== null) {
                $record = $form->repository()->edit($recordId);

                if ($record instanceof Administrator) {
                    $rolesField->value($record->roles->pluck('id')->all());
                }
            }

            $form->pushField($rolesField);

            $form->saving(function (Form $form): void {
                $password = (string) $form->input('password', '');

                if ($password === '') {
                    $form->forgetInput('password');

                    return;
                }

                $form->setInput('password', Hash::make($password));
            });
        });
    }

    protected function authorizeDestroy(mixed $record): ?JsonResponse
    {
        $currentId = Admin::id();

        if ($currentId !== null && $record instanceof Model && (int) $record->getKey() === $currentId) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete current user.',
            ], 403);
        }

        return null;
    }
}
