<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Form;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class OperationLogController extends ResourceController
{
    protected string $title = 'Operation Log';

    protected string $description = 'Audit trail of admin operation requests';

    protected function model(): string
    {
        return config('blatui-admin.database.operation_log_model', OperationLog::class);
    }

    protected function resourceKey(): string
    {
        return 'logs';
    }

    protected function resource(): string
    {
        return Admin::url('auth/logs');
    }

    protected function grid(): Grid
    {
        return Grid::make($this->model(), function (Grid $grid): void {
            $grid->resource($this->resource());

            $grid->disableCreateButton();

            $userModel = config('blatui-admin.database.users_model', Administrator::class);
            $grid->model()->with(['user']);

            $grid->column('id', 'ID')->sortable();

            $grid->column('user_id', 'Operator')
                ->display(function (mixed $value, mixed $row = null): string {
                    if ($row instanceof OperationLog && $row->user instanceof Administrator) {
                        return $row->user->name ?: $row->user->username;
                    }

                    return $value !== null ? (string) $value : 'Guest';
                });

            $grid->column('method', 'Method')
                ->badge(['GET' => 'info', 'POST' => 'success', 'PUT' => 'warning', 'PATCH' => 'warning', 'DELETE' => 'danger']);

            $grid->column('path', 'Path');

            $grid->column('ip', 'IP');

            $grid->column('input', 'Input')
                ->display(function (mixed $value): string {
                    if ($value === null || $value === '') {
                        return '';
                    }

                    $json = is_array($value)
                        ? (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '')
                        : (string) $value;

                    return mb_strimwidth($json, 0, 100, '...');
                });

            $grid->column('created_at', 'Created At')->datetime();

            $grid->filter(function (Filter $filter): void {
                $filter->equal('user_id', 'Operator ID');
                $filter->equal('method', 'Method');
                $filter->like('path', 'Path');
                $filter->like('ip', 'IP');
                $filter->between('created_at', 'Created At');
            });

            $grid->actions(function (Row $actions): void {
                $actions->disableShow();
                $actions->disableEdit();
                $actions->disableQuickEdit();
            });
        });
    }

    protected function form(bool $editing, ?int $id = null): Form
    {
        throw new LogicException('Form editing/creation is not supported for operation logs.');
    }

    /**
     * Display the blank resource form (Blocked).
     */
    public function create(): Content
    {
        abort(404);
    }

    /**
     * Store a newly submitted record (Blocked).
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        abort(404);
    }

    /**
     * Display the form for an existing record (Blocked).
     */
    public function edit(int $id): Content
    {
        abort(404);
    }

    /**
     * Update an existing record (Blocked).
     */
    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        abort(404);
    }
}
