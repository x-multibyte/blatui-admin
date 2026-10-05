<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Form;
use BlatUI\Admin\Grid;
use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Repositories\EloquentRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Throwable;

abstract class ResourceController extends AdminController
{
    /**
     * Eloquent model class-string the resource manages.
     *
     * Returns an empty string until a subclass overrides it; repositoryFor()
     * turns that into a named exception rather than a constructor TypeError.
     */
    protected function model(): string
    {
        return '';
    }

    /**
     * Configuration key of the resource, used to build its URL prefix.
     */
    protected function resourceKey(): string
    {
        return '';
    }

    /**
     * URL prefix every action of this resource lives under.
     */
    protected function resource(): string
    {
        return Admin::url('auth/'.$this->resourceKey());
    }

    /**
     * Build the repository backing the resource.
     */
    protected function repositoryFor(): EloquentRepository
    {
        $model = $this->model();

        if (! is_subclass_of($model, Model::class)) {
            throw new LogicException('Subclass must implement model().');
        }

        return new EloquentRepository($model);
    }

    /**
     * Build the resource listing grid.
     */
    protected function grid(): Grid
    {
        throw new LogicException('Subclass must implement grid().');
    }

    /**
     * Build the resource form. Subclasses set their own fields and prefill
     * them when editing; the base only pins the action and redirect URLs.
     */
    protected function form(bool $editing, ?int $id = null): Form
    {
        throw new LogicException('Subclass must implement form().');
    }

    /**
     * Display the resource listing.
     */
    public function index(): Content
    {
        return $this->content()
            ->breadcrumb(['text' => $this->title()])
            ->row($this->grid());
    }

    /**
     * Display the blank resource form.
     */
    public function create(): Content
    {
        return $this->content()
            ->breadcrumb(['text' => $this->title()])
            ->row($this->formFor(false));
    }

    /**
     * Store a newly submitted record.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        try {
            return $this->formFor(false)->store($request);
        } catch (Throwable $e) {
            Admin::logger()->error('Resource store failed: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Display the form for an existing record.
     */
    public function edit(int $id): Content
    {
        return $this->content()
            ->breadcrumb(['text' => $this->title()])
            ->row($this->formFor(true, $id)->edit($id));
    }

    /**
     * Update an existing record.
     */
    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        try {
            return $this->formFor(true, $id)->update($id, $request);
        } catch (Throwable $e) {
            Admin::logger()->error('Resource update failed: '.$e->getMessage(), [
                'id' => $id,
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a record.
     */
    public function destroy(int $id): JsonResponse
    {
        $repository = $this->repositoryFor();
        $record = $repository->edit($id);

        // 404 before the authorization check: probing for existence must not
        // leak whether the caller would have been allowed to delete the record.
        if (! $record instanceof Model) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        $refused = $this->authorizeDestroy($record);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        try {
            $repository->destroy($id);
        } catch (Throwable $e) {
            Admin::logger()->error('Resource destroy failed: '.$e->getMessage(), [
                'id' => $id,
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return response()->json([
            'status' => true,
            'message' => (string) __('blatui-admin::admin.deleted'),
        ]);
    }

    /**
     * Delete many records at once.
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $repository = $this->repositoryFor();
        $ids = $validator->validated()['ids'];

        // Look every record up before deleting any of them. The same 404-before-
        // authorization discipline destroy() uses applies here: a missing record
        // must not leak whether the caller would have been allowed to delete it,
        // so the whole request is refused before authorizeDestroy() ever sees it.
        $records = [];

        foreach ($ids as $id) {
            $record = $repository->edit($id);

            if (! $record instanceof Model) {
                return response()->json([
                    'status' => false,
                    'message' => 'Record not found.',
                ], 404);
            }

            $records[] = $record;
        }

        // All-or-nothing: one refusal aborts the whole batch rather than deleting
        // the permitted subset and leaving the admin to guess which rows survived.
        foreach ($records as $record) {
            $refused = $this->authorizeDestroy($record);

            if ($refused instanceof JsonResponse) {
                return $refused;
            }
        }

        try {
            $repository->destroy($ids);
        } catch (Throwable $e) {
            Admin::logger()->error('Resource batch destroy failed: '.$e->getMessage(), [
                'ids' => $ids,
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return response()->json([
            'status' => true,
            'message' => (string) __('blatui-admin::admin.deleted'),
        ]);
    }

    /**
     * Decide whether the record may be deleted.
     *
     * Returning a response refuses the delete; returning null allows it. The
     * base class has no opinion, so subclasses opt in to their own guards.
     * Applies to destroy() and batchDestroy() alike, so a guard added here can
     * never be sidestepped by selecting the row's checkbox instead of its button.
     */
    protected function authorizeDestroy(mixed $record): ?JsonResponse
    {
        return null;
    }

    /**
     * Build the resource form with its action and redirect pinned to the
     * resource URL, so every form posts back to its own listing.
     */
    private function formFor(bool $editing, ?int $id = null): Form
    {
        $form = $this->form($editing, $id);

        $form->action($this->resource());
        $form->redirect($this->resource());

        return $form;
    }
}
