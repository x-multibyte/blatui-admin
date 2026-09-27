<?php

declare(strict_types=1);

namespace BlatUI\Admin;

use BlatUI\Admin\Grid\Column;
use BlatUI\Admin\Grid\Filter;
use BlatUI\Admin\Grid\Model;
use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Grid\Tools;
use BlatUI\Admin\Layout\Content;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class Grid implements Htmlable, Renderable, Responsable
{
    /**
     * Grid model coordinator.
     */
    protected Model $model;

    /**
     * Registered columns collection.
     *
     * @var Collection<string, Column>
     */
    protected Collection $columns;

    /**
     * Filter instance.
     */
    protected Filter $filter;

    /**
     * Tools instance.
     */
    protected Tools $tools;

    /**
     * Page title.
     */
    protected ?string $title = null;

    /**
     * Page description.
     */
    protected ?string $description = null;

    /**
     * Resource URI prefix.
     */
    protected ?string $resource = null;

    /**
     * Row actions configuration callback.
     */
    protected ?Closure $actionsCallback = null;

    /**
     * Whether all row actions are disabled globally.
     */
    protected bool $actionsDisabled = false;

    /**
     * Whether filter is disabled.
     */
    protected bool $filterDisabled = false;

    /**
     * Cached rows collection.
     *
     * @var Collection<int, Row>|null
     */
    protected ?Collection $cachedRows = null;

    /**
     * Resolved paginator instance.
     */
    protected mixed $paginator = null;

    /**
     * Custom view template.
     */
    protected string $view = 'blatui-admin::grid.table';

    /**
     * Create a new Grid instance.
     */
    final public function __construct(mixed $repository = null, ?Closure $callback = null)
    {
        $this->columns = collect();

        if ($repository instanceof Model) {
            $this->model = $repository;
        } else {
            $this->model = new Model($repository);
        }

        try {
            $modelInstance = $this->model->getModel();
        } catch (Throwable) {
            $modelInstance = $repository;
        }

        $this->filter = new Filter($modelInstance);
        $this->model->setFilter($this->filter);

        $this->tools = new Tools($this);

        if ($callback instanceof Closure) {
            $callback($this);
        }
    }

    /**
     * Static factory method.
     */
    public static function make(mixed $repository = null, ?Closure $callback = null): static
    {
        return new static($repository, $callback);
    }

    /**
     * Define or register a column.
     */
    public function column(string $name, ?string $label = null): Column
    {
        $column = new Column($name, $label);
        $this->columns->put($name, $column);

        return $column;
    }

    /**
     * Get registered columns.
     *
     * @return Collection<string, Column>
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    /**
     * Get underlying Grid model coordinator.
     */
    public function model(): Model
    {
        return $this->model;
    }

    /**
     * Configure or retrieve filter.
     */
    public function filter(?Closure $callback = null): Filter
    {
        if ($callback instanceof Closure) {
            $callback($this->filter);
        }

        return $this->filter;
    }

    /**
     * Configure or retrieve tools.
     */
    public function tools(?Closure $callback = null): Tools
    {
        if ($callback instanceof Closure) {
            $callback($this->tools);
        }

        return $this->tools;
    }

    /**
     * Configure row actions via callback.
     */
    public function actions(?Closure $callback = null): static
    {
        if ($callback instanceof Closure) {
            $this->actionsCallback = $callback;
        }

        return $this;
    }

    /**
     * Configure batch actions via callback.
     */
    public function batchActions(?Closure $callback = null): static
    {
        if ($callback instanceof Closure) {
            $this->tools->batchActions($callback);
        }

        return $this;
    }

    /**
     * Configure pagination.
     */
    public function paginate(int|bool $perPage = 20): static
    {
        if (is_bool($perPage)) {
            $this->model->paginate($perPage);
        } else {
            $this->model->paginate(true);
            $this->model->setPerPage($perPage);
        }

        return $this;
    }

    /**
     * Disable Create button.
     */
    public function disableCreateButton(bool $disable = true): static
    {
        $this->tools->disableCreateButton($disable);

        return $this;
    }

    /**
     * Disable Refresh button.
     */
    public function disableRefreshButton(bool $disable = true): static
    {
        $this->tools->disableRefreshButton($disable);

        return $this;
    }

    /**
     * Disable Filter button.
     */
    public function disableFilterButton(bool $disable = true): static
    {
        $this->tools->disableFilterButton($disable);

        return $this;
    }

    /**
     * Disable Batch actions.
     */
    public function disableBatchActions(bool $disable = true): static
    {
        $this->tools->disableBatchActions($disable);

        return $this;
    }

    /**
     * Disable all row actions.
     */
    public function disableActions(bool $disable = true): static
    {
        $this->actionsDisabled = $disable;

        return $this;
    }

    /**
     * Check if row actions are disabled globally.
     */
    public function isActionsDisabled(): bool
    {
        return $this->actionsDisabled;
    }

    /**
     * Disable pagination.
     */
    public function disablePagination(bool $disable = true): static
    {
        $this->model->paginate(! $disable);

        return $this;
    }

    /**
     * Disable filter.
     */
    public function disableFilter(bool $disable = true): static
    {
        $this->filterDisabled = $disable;
        $this->tools->disableFilterButton($disable);
        $this->model->setFilter($disable ? null : $this->filter);

        return $this;
    }

    /**
     * Check if filter is disabled.
     */
    public function isFilterDisabled(): bool
    {
        return $this->filterDisabled;
    }

    /**
     * Get or set resource URI prefix.
     */
    public function resource(?string $resource = null): ?string
    {
        if ($resource !== null) {
            $this->resource = $resource;
            $this->tools->resource($resource);
        }

        if ($this->resource !== null) {
            return $this->resource;
        }

        try {
            $model = $this->model->getModel();

            return Admin::url($model->getTable());
        } catch (Throwable) {
            if (function_exists('request') && ($path = trim((string) request()->path(), '/')) !== '') {
                return '/'.$path;
            }

            return null;
        }
    }

    /**
     * Set resource URI prefix fluently.
     */
    public function setResource(string $resource): static
    {
        $this->resource($resource);

        return $this;
    }

    /**
     * Get resource URI prefix.
     */
    public function getResource(): ?string
    {
        return $this->resource();
    }

    /**
     * Get or set page title.
     */
    public function title(?string $title = null): ?string
    {
        if ($title !== null) {
            $this->title = $title;
        }

        return $this->title;
    }

    /**
     * Set page title fluently.
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get page title.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Get or set page description.
     */
    public function description(?string $description = null): ?string
    {
        if ($description !== null) {
            $this->description = $description;
        }

        return $this->description;
    }

    /**
     * Set page description fluently.
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get page description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get resolved paginator instance.
     */
    public function paginator(): mixed
    {
        if ($this->cachedRows === null) {
            $this->rows();
        }

        return $this->paginator;
    }

    /**
     * Build and retrieve rows collection.
     *
     * @return Collection<int, Row>
     */
    public function rows(): Collection
    {
        if ($this->cachedRows !== null) {
            return $this->cachedRows;
        }

        $data = $this->model->buildData();

        if ($data instanceof LengthAwarePaginator) {
            $this->paginator = $data;
            $items = $data->items();
        } else {
            $this->paginator = null;
            $items = $data->all();
        }

        $resource = $this->resource();
        $keyName = $this->model->getKeyName();

        /** @var Collection<int, Row> $rows */
        $rows = collect();

        foreach ($items as $index => $item) {
            $row = new Row($item, $index, $resource);
            $row->setKeyName($keyName);

            if ($this->actionsDisabled) {
                $row->disableActions();
            }

            if ($this->actionsCallback !== null) {
                $row->actions($this->actionsCallback);
            }

            $rows->push($row);
        }

        $this->cachedRows = $rows;

        return $rows;
    }

    /**
     * Get current sort direction for a column.
     */
    public function getSortDirection(string $column): ?string
    {
        $sorts = $this->model->resolveSortFromRequest();

        return $sorts[$column] ?? null;
    }

    /**
     * Generate sortable link URL for a column.
     */
    public function getSortUrl(string $column): string
    {
        $current = $this->getSortDirection($column);
        $direction = $current === 'asc' ? 'desc' : 'asc';

        /** @var array<string, mixed> $query */
        $query = request()->query();
        $query['_sort'] = [
            'column' => $column,
            'type' => $direction,
        ];
        unset($query['page']);

        $url = request()->url();

        return $url.'?'.http_build_query($query);
    }

    /**
     * Set or get custom view template.
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
     * Get view template name.
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Render the table HTML.
     */
    public function render(): string
    {
        $rows = $this->rows();

        $gridProxy = new class($this)
        {
            public function __construct(protected Grid $grid) {}

            /**
             * @param  array<int, mixed>  $args
             */
            public function __call(string $method, array $args): mixed
            {
                return $this->grid->{$method}(...$args);
            }

            public function __get(string $name): mixed
            {
                return $this->grid->{$name};
            }
        };

        $toolsProxy = new class($this->tools) implements Htmlable
        {
            public function __construct(protected Tools $tools) {}

            public function render(): string
            {
                return $this->tools->render();
            }

            public function toHtml(): string
            {
                return $this->tools->toHtml();
            }

            /**
             * @param  array<int, mixed>  $args
             */
            public function __call(string $method, array $args): mixed
            {
                return $this->tools->{$method}(...$args);
            }

            public function __toString(): string
            {
                return $this->tools->render();
            }
        };

        $filterProxy = new class($this->filter) implements Htmlable
        {
            public function __construct(protected Filter $filter) {}

            public function render(): string
            {
                return $this->filter->render();
            }

            public function toHtml(): string
            {
                return $this->filter->toHtml();
            }

            /**
             * @param  array<int, mixed>  $args
             */
            public function __call(string $method, array $args): mixed
            {
                return $this->filter->{$method}(...$args);
            }

            public function __toString(): string
            {
                return $this->filter->render();
            }
        };

        if (view()->exists($this->view)) {
            return view($this->view, [
                'grid' => $gridProxy,
                'rows' => $rows,
                'columns' => $this->columns,
                'tools' => $toolsProxy,
                'filter' => $filterProxy,
                'paginator' => $this->paginator,
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

        if ($this->description !== null && $this->description !== '') {
            $content->description($this->description);
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

    /**
     * Magic caller to register columns fluently.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $label = isset($arguments[0]) && is_string($arguments[0]) ? $arguments[0] : null;

        return $this->column($method, $label);
    }
}
