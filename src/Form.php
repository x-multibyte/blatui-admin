<?php

declare(strict_types=1);

namespace BlatUI\Admin;

use BlatUI\Admin\Contracts\Repository;
use BlatUI\Admin\Form\Field;
use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Repositories\EloquentRepository;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class Form implements Htmlable, Renderable, Responsable, Stringable
{
    /**
     * Underlying repository instance.
     */
    protected ?Repository $repository = null;

    /**
     * Primary key of the record being edited (null when creating).
     */
    protected mixed $key = null;

    /**
     * Registered field instances.
     *
     * @var array<int, Field>
     */
    protected array $fields = [];

    /**
     * Form lifecycle hook callbacks.
     *
     * @var array<string, array<int, Closure>>
     */
    protected array $hooks = [
        'saving' => [],
        'saved' => [],
        'creating' => [],
        'created' => [],
        'updating' => [],
        'updated' => [],
    ];

    /**
     * Form field data buffer.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Form submission input values.
     *
     * @var array<string, mixed>
     */
    protected array $inputs = [];

    /**
     * Form action URL.
     */
    protected string $action = '';

    /**
     * Form HTTP method.
     */
    protected string $method = 'POST';

    /**
     * Form card title.
     */
    protected ?string $title = null;

    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.container';

    /**
     * Custom redirect URL after successful submission.
     */
    protected ?string $redirectUrl = null;

    /**
     * Create a new form instance.
     *
     * @param  mixed  $repository  Repository, Model instance, Model class name, or Builder
     */
    final public function __construct(mixed $repository = null)
    {
        if ($repository !== null) {
            $this->initRepository($repository);
        }
    }

    /**
     * Initialize form repository instance.
     */
    protected function initRepository(mixed $repository): void
    {
        if ($repository instanceof Repository) {
            $this->repository = $repository;
        } elseif ($repository instanceof Model || $repository instanceof Builder || is_string($repository)) {
            /** @var Model|Builder<Model>|class-string<Model> $repository */
            $this->repository = new EloquentRepository($repository);
        } elseif ($repository !== null) {
            /** @var Model $repository */
            $this->repository = new EloquentRepository($repository);
        }
    }

    /**
     * Register a callback to be run while the form is resolving.
     */
    public static function resolving(Closure $callback): void
    {
        Container::getInstance()->resolving(static::class, function (Form $instance, Container $container) use ($callback): void {
            $callback($instance, $container);
        });
    }

    /**
     * Register a callback to be run after the form is resolved.
     */
    public static function resolved(Closure $callback): void
    {
        Container::getInstance()->afterResolving(static::class, function (Form $instance, Container $container) use ($callback): void {
            $callback($instance, $container);
        });
    }

    /**
     * Create and initialize a new form instance resolving through the service container.
     */
    public static function make(mixed $repository = null, ?Closure $callback = null): static
    {
        /** @var static $form */
        $form = Container::getInstance()->make(static::class, ['repository' => $repository]);

        if ($callback instanceof Closure) {
            $callback($form);
        }

        return $form;
    }

    /**
     * Add a text input field.
     */
    public function text(string $column, ?string $label = null): Field\Text
    {
        $field = new Field\Text($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Add a textarea field.
     */
    public function textarea(string $column, ?string $label = null): Field\Textarea
    {
        $field = new Field\Textarea($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Add a select dropdown field.
     */
    public function select(string $column, ?string $label = null): Field\Select
    {
        $field = new Field\Select($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Add a switch toggle field.
     */
    public function switch(string $column, ?string $label = null): Field\SwitchField
    {
        $field = new Field\SwitchField($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Add a date/time field.
     */
    public function datetime(string $column, ?string $label = null): Field\Datetime
    {
        $field = new Field\Datetime($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Add a hidden input field.
     */
    public function hidden(string $column, ?string $label = null): Field\Hidden
    {
        $field = new Field\Hidden($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Add a readonly display field.
     */
    public function display(string $column, ?string $label = null): Field\Display
    {
        $field = new Field\Display($column, $label);
        $this->pushField($field);

        return $field;
    }

    /**
     * Register a field with the form.
     */
    public function pushField(Field $field): static
    {
        $field->setForm($this);

        if (array_key_exists($field->getColumn(), $this->data)) {
            $field->value($this->data[$field->getColumn()]);
        }

        $this->fields[] = $field;

        return $this;
    }

    /**
     * Get all registered fields.
     *
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Get all registered relation fields.
     *
     * @return array<int, Field\Relation>
     */
    protected function relationFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (Field $field): bool => $field instanceof Field\Relation,
        ));
    }

    /**
     * Sync every relation field's submitted input onto the pivot table.
     *
     * A pivot sync failure is intentionally left uncaught so the whole
     * request fails rather than silently persisting a partial record.
     * The same applies when there is nothing to sync against: a repository
     * that does not hand back an Eloquent model throws instead of dropping
     * every pivot write and still reporting success.
     */
    protected function syncRelations(mixed $record): void
    {
        $relationFields = $this->relationFields();

        if ($relationFields === []) {
            return;
        }

        if (! $record instanceof Model) {
            throw new RuntimeException(sprintf(
                'Cannot sync relation fields: the repository returned [%s] instead of an Eloquent model.',
                get_debug_type($record),
            ));
        }

        try {
            foreach ($relationFields as $field) {
                $record->{$field->getRelation()}()->sync((array) ($this->inputs[$field->getColumn()] ?? []));
            }
        } catch (Throwable $e) {
            Admin::logger()->error('Failed to sync relations: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Register a saving lifecycle hook.
     */
    public function saving(Closure $callback): static
    {
        $this->hooks['saving'][] = $callback;

        return $this;
    }

    /**
     * Register a saved lifecycle hook.
     */
    public function saved(Closure $callback): static
    {
        $this->hooks['saved'][] = $callback;

        return $this;
    }

    /**
     * Register a creating lifecycle hook.
     */
    public function creating(Closure $callback): static
    {
        $this->hooks['creating'][] = $callback;

        return $this;
    }

    /**
     * Register a created lifecycle hook.
     */
    public function created(Closure $callback): static
    {
        $this->hooks['created'][] = $callback;

        return $this;
    }

    /**
     * Register an updating lifecycle hook.
     */
    public function updating(Closure $callback): static
    {
        $this->hooks['updating'][] = $callback;

        return $this;
    }

    /**
     * Register an updated lifecycle hook.
     */
    public function updated(Closure $callback): static
    {
        $this->hooks['updated'][] = $callback;

        return $this;
    }

    /**
     * Load a record by its primary key for editing.
     */
    public function edit(mixed $id): static
    {
        $this->key = $id;
        $this->method = 'PUT';

        $record = $this->repository()->edit($id);

        if ($record instanceof Arrayable) {
            $this->fill($record->toArray());
        } elseif (is_array($record)) {
            $this->fill($record);
        }

        if ($record instanceof Model) {
            foreach ($this->relationFields() as $field) {
                $relationName = $field->getRelation();

                if ($relationName !== '' && method_exists($record, $relationName)) {
                    $relation = $record->{$relationName}();

                    if ($relation instanceof BelongsToMany) {
                        $keyName = $relation->getRelated()->getKeyName();
                        /** @var Collection<int, mixed> $collection */
                        $collection = $record->relationLoaded($relationName)
                            ? $record->getRelation($relationName)
                            : $relation->get();
                        $field->value($collection->pluck($keyName)->all());
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Fill the form with data.
     *
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        foreach ($this->fields as $field) {
            if (array_key_exists($field->getColumn(), $this->data)) {
                $field->value($this->data[$field->getColumn()]);
            }
        }

        return $this;
    }

    /**
     * Determine if the form is in creating mode.
     */
    public function isCreating(): bool
    {
        return $this->key === null;
    }

    /**
     * Determine if the form is in editing mode.
     */
    public function isEditing(): bool
    {
        return $this->key !== null;
    }

    /**
     * Get the record key being edited.
     */
    public function getKey(): mixed
    {
        return $this->key;
    }

    /**
     * Get or set the underlying repository instance.
     */
    public function repository(mixed $repository = null): Repository
    {
        if ($repository !== null) {
            $this->initRepository($repository);
        }

        if ($this->repository === null) {
            throw new RuntimeException('Form repository is not initialized.');
        }

        return $this->repository;
    }

    /**
     * Get an input value or all input values.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->inputs;
        }

        return data_get($this->inputs, $key, $default);
    }

    /**
     * Set a submission input value.
     */
    public function setInput(string $key, mixed $value): static
    {
        data_set($this->inputs, $key, $value);

        return $this;
    }

    /**
     * Remove a submission input value.
     */
    public function forgetInput(string $key): static
    {
        Arr::forget($this->inputs, $key);

        return $this;
    }

    /**
     * Get validation rules compiled from registered fields.
     *
     * @return array<string, array<int|string, mixed>|string>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $fieldRules = $field->getRules();

            if (! empty($fieldRules)) {
                $rules[$field->getColumn()] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Get custom validation messages compiled from registered fields.
     *
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        $messages = [];

        foreach ($this->fields as $field) {
            foreach ($field->getValidationMessages() as $rule => $message) {
                $key = str_contains($rule, '.') ? $rule : $field->getColumn().'.'.$rule;
                $messages[$key] = $message;
            }
        }

        return $messages;
    }

    /**
     * Handle form store submission.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make(
            $request->all(),
            $this->validationRules(),
            $this->validationMessages(),
        );

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $this->inputs = $request->except(['_token', '_method']);

        $savingResult = $this->callHooks('saving', $this);

        if ($savingResult === false) {
            return $this->errorResponse($request, 'Saving aborted.');
        }

        if ($savingResult instanceof JsonResponse || $savingResult instanceof RedirectResponse) {
            return $savingResult;
        }

        $creatingResult = $this->callHooks('creating', $this);

        if ($creatingResult === false) {
            return $this->errorResponse($request, 'Creating aborted.');
        }

        if ($creatingResult instanceof JsonResponse || $creatingResult instanceof RedirectResponse) {
            return $creatingResult;
        }

        $data = $this->prepareDataForSave();
        $record = $this->repository()->store($data);

        $this->syncRelations($record);

        $this->callHooks('created', $this, $record);
        $this->callHooks('saved', $this, $record);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'Created successfully.',
                'data' => $record,
            ]);
        }

        return $this->successRedirect();
    }

    /**
     * Handle form update submission.
     */
    public function update(mixed $id, Request $request): JsonResponse|RedirectResponse
    {
        $this->key = $id;

        $validator = Validator::make(
            $request->all(),
            $this->validationRules(),
            $this->validationMessages(),
        );

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $this->inputs = $request->except(['_token', '_method']);

        $savingResult = $this->callHooks('saving', $this);

        if ($savingResult === false) {
            return $this->errorResponse($request, 'Saving aborted.');
        }

        if ($savingResult instanceof JsonResponse || $savingResult instanceof RedirectResponse) {
            return $savingResult;
        }

        $updatingResult = $this->callHooks('updating', $this);

        if ($updatingResult === false) {
            return $this->errorResponse($request, 'Updating aborted.');
        }

        if ($updatingResult instanceof JsonResponse || $updatingResult instanceof RedirectResponse) {
            return $updatingResult;
        }

        $data = $this->prepareDataForSave();
        $success = $this->repository()->update($id, $data);

        if ($success) {
            $this->syncRelations($this->repository()->edit($id));
        }

        $this->callHooks('updated', $this);
        $this->callHooks('saved', $this);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => (bool) $success,
                'message' => $success ? 'Updated successfully.' : 'Failed to update.',
            ]);
        }

        return $this->successRedirect();
    }

    /**
     * Call registered lifecycle hooks.
     */
    protected function callHooks(string $name, mixed ...$args): mixed
    {
        foreach ($this->hooks[$name] ?? [] as $callback) {
            $result = $callback(...$args);

            if ($result === false || $result instanceof JsonResponse || $result instanceof RedirectResponse || $result instanceof SymfonyResponse) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Prepare input data for persistence.
     *
     * @return array<string, mixed>
     */
    protected function prepareDataForSave(): array
    {
        $ignoredColumns = [];
        $data = [];

        foreach ($this->fields as $field) {
            if ($field instanceof Field\Display || $field instanceof Field\Relation) {
                $ignoredColumns[] = $field->getColumn();

                continue;
            }

            $column = $field->getColumn();

            if (array_key_exists($column, $this->inputs)) {
                $data[$column] = $this->inputs[$column];
            } elseif ($field instanceof Field\SwitchField) {
                $data[$column] = 0;
            } elseif ($this->isCreating() && $field->getDefault() !== null) {
                $data[$column] = $field->getDefault();
            }
        }

        foreach ($this->inputs as $key => $value) {
            if (! str_starts_with($key, '_') && ! in_array($key, $ignoredColumns, true)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Generate an error response for hook failures.
     */
    protected function errorResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], 400);
        }

        return redirect()->back(fallback: '/')->withErrors(['error' => $message])->withInput();
    }

    /**
     * Generate a successful redirect response.
     */
    protected function successRedirect(): RedirectResponse
    {
        if ($this->redirectUrl !== null && $this->redirectUrl !== '') {
            return redirect()->to($this->redirectUrl);
        }

        if ($this->action !== '') {
            return redirect()->to($this->action);
        }

        return redirect()->back(fallback: '/');
    }

    /**
     * Set or get the form action URL.
     */
    public function action(?string $action = null): static|string
    {
        if ($action === null) {
            return $this->action;
        }

        $this->action = $action;

        return $this;
    }

    /**
     * Set or get the form HTTP method.
     */
    public function method(?string $method = null): static|string
    {
        if ($method === null) {
            return $this->method;
        }

        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Set or get the form card title.
     */
    public function title(?string $title = null): static|string|null
    {
        if ($title === null) {
            return $this->title;
        }

        $this->title = $title;

        return $this;
    }

    /**
     * Get the form card title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set or get custom Blade view template.
     */
    public function view(?string $view = null): static|string
    {
        if ($view === null) {
            return $this->view;
        }

        $this->view = $view;

        return $this;
    }

    /**
     * Set custom redirect URL.
     */
    public function redirect(string $url): static
    {
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * Render the form container HTML.
     */
    public function render(): string
    {
        $formProxy = new class($this)
        {
            public function __construct(protected Form $form) {}

            /**
             * @param  array<int, mixed>  $args
             */
            public function __call(string $method, array $args): mixed
            {
                return $this->form->{$method}(...$args);
            }

            public function __get(string $name): mixed
            {
                return $this->form->{$name};
            }
        };

        if (function_exists('view') && view()->exists($this->view)) {
            return view($this->view, [
                'form' => $formProxy,
                'action' => $this->action,
                'method' => $this->method,
                'title' => $this->title,
                'fields' => $this->fields,
                'isEditing' => $this->isEditing(),
            ])->render();
        }

        return '';
    }

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): SymfonyResponse
    {
        $content = Content::make();

        if ($this->title !== null && $this->title !== '') {
            $content->title($this->title);
        }

        $content->body($this->render());

        return $content->toResponse($request);
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
