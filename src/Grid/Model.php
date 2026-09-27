<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use BlatUI\Admin\Contracts\Repository;
use BlatUI\Admin\Repositories\EloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use RuntimeException;

class Model
{
    /**
     * The repository instance.
     */
    protected Repository $repository;

    /**
     * Number of items per page.
     */
    protected int $perPage = 20;

    /**
     * The query string parameter name for the page.
     */
    protected string $pageName = 'page';

    /**
     * The query string parameter name for items per page.
     */
    protected string $perPageName = 'per_page';

    /**
     * Whether pagination is enabled.
     */
    protected bool $paginated = true;

    /**
     * Relations to eager load.
     *
     * @var array<int|string, mixed>
     */
    protected array $relations = [];

    /**
     * Programmatic order by clauses.
     *
     * @var array<int, array{column: string, direction: 'asc'|'desc'}>
     */
    protected array $orders = [];

    /**
     * Callbacks applied to the query builder.
     *
     * @var array<int, callable(Builder<EloquentModel>): void>
     */
    protected array $queryCallbacks = [];

    /**
     * Optional filter criteria handler.
     */
    protected mixed $filter = null;

    /**
     * Create a new Grid Model coordinator instance.
     *
     * @param  Repository|EloquentModel|Builder<EloquentModel>|class-string<EloquentModel>  $model
     */
    public function __construct(Repository|EloquentModel|Builder|string $model)
    {
        if ($model instanceof Repository) {
            $this->repository = $model;
        } else {
            $this->repository = new EloquentRepository($model);
        }
    }

    /**
     * Get the underlying repository.
     */
    public function repository(): Repository
    {
        return $this->repository;
    }

    /**
     * Get the primary key name for the model.
     */
    public function getKeyName(): string
    {
        return $this->repository->getKeyName();
    }

    /**
     * Get the underlying Eloquent model instance.
     */
    public function getModel(): EloquentModel
    {
        if (method_exists($this->repository, 'getModel')) {
            /** @var EloquentModel */
            return $this->repository->getModel();
        }

        $model = $this->repository->model();

        if ($model instanceof EloquentModel) {
            return $model;
        }

        if ($model instanceof Builder) {
            return $model->getModel();
        }

        throw new RuntimeException('Repository does not have an Eloquent Model.');
    }

    /**
     * Set the number of items per page.
     */
    public function setPerPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Get the number of items per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Set the query string parameter name for the page.
     */
    public function setPageName(string $name): static
    {
        $this->pageName = $name;

        return $this;
    }

    /**
     * Get the query string parameter name for the page.
     */
    public function getPageName(): string
    {
        return $this->pageName;
    }

    /**
     * Set the query string parameter name for items per page.
     */
    public function setPerPageName(string $name): static
    {
        $this->perPageName = $name;

        return $this;
    }

    /**
     * Get the query string parameter name for items per page.
     */
    public function getPerPageName(): string
    {
        return $this->perPageName;
    }

    /**
     * Set relations to eager load.
     *
     * @param  array<int|string, mixed>|string  $relations
     */
    public function with(array|string $relations): static
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->relations = array_merge($this->relations, $relations);

        return $this;
    }

    /**
     * Get eager loaded relations.
     *
     * @return array<int|string, mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Add an order by clause.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
        ];

        return $this;
    }

    /**
     * Get the order by clauses.
     *
     * @return array<int, array{column: string, direction: 'asc'|'desc'}>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * Add a where condition to the query.
     */
    public function where(mixed ...$params): static
    {
        $this->queryCallbacks[] = function (Builder $query) use ($params): void {
            $query->where(...$params);
        };

        return $this;
    }

    /**
     * Set whether pagination is enabled.
     */
    public function paginate(bool $paginate = true): static
    {
        $this->paginated = $paginate;

        return $this;
    }

    /**
     * Determine whether pagination is enabled.
     */
    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    /**
     * Add a custom query callback.
     *
     * @param  callable(Builder<EloquentModel>): void  $callback
     */
    public function addQueryCallback(callable $callback): static
    {
        $this->queryCallbacks[] = $callback;

        return $this;
    }

    /**
     * Set a filter handler.
     */
    public function setFilter(mixed $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Get the filter handler.
     */
    public function getFilter(): mixed
    {
        return $this->filter;
    }

    /**
     * Get a new query builder instance from the repository.
     *
     * @return Builder<EloquentModel>
     */
    public function newQuery(): Builder
    {
        if (method_exists($this->repository, 'newQuery')) {
            /** @var Builder<EloquentModel> */
            return $this->repository->newQuery();
        }

        $model = $this->repository->model();

        if ($model instanceof Builder) {
            /** @var Builder<EloquentModel> */
            return clone $model;
        }

        if ($model instanceof EloquentModel) {
            /** @var Builder<EloquentModel> */
            return $model->newQuery();
        }

        throw new RuntimeException('Unable to create query builder from repository.');
    }

    /**
     * Resolve sorting parameters from the current request.
     *
     * @return array<string, 'asc'|'desc'> Map of column => direction ('asc'|'desc')
     */
    public function resolveSortFromRequest(): array
    {
        $request = request();

        $sort = $request->query('_sort');

        if ($sort === null || $sort === '') {
            $sort = $request->input('_sort');
        }

        if (empty($sort)) {
            return [];
        }

        /** @var array<string, 'asc'|'desc'> $results */
        $results = [];

        if (is_array($sort)) {
            // Format 1: _sort[column]=col&_sort[type]=desc|asc (or direction)
            if (isset($sort['column']) && is_string($sort['column'])) {
                $column = $sort['column'];
                $direction = isset($sort['type']) ? (string) $sort['type'] : ($sort['direction'] ?? 'asc');
                $results[$column] = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

                return $results;
            }

            // Format 2: _sort[column_name]=desc|asc
            foreach ($sort as $col => $dir) {
                if (is_string($col) && is_string($dir)) {
                    $results[$col] = strtolower($dir) === 'desc' ? 'desc' : 'asc';
                }
            }

            return $results;
        }

        // Format 3: _sort=id,desc or _sort=-id or _sort=id
        if (is_string($sort)) {
            if (str_starts_with($sort, '-')) {
                $results[substr($sort, 1)] = 'desc';
            } elseif (str_contains($sort, ',')) {
                [$col, $dir] = explode(',', $sort, 2);
                $results[$col] = strtolower($dir) === 'desc' ? 'desc' : 'asc';
            } else {
                $results[$sort] = 'asc';
            }
        }

        return $results;
    }

    /**
     * Apply sorting to query builder.
     *
     * @param  Builder<EloquentModel>  $query
     */
    protected function applySorting(Builder $query): void
    {
        $requestSort = $this->resolveSortFromRequest();

        if (! empty($requestSort)) {
            foreach ($requestSort as $column => $direction) {
                $query->orderBy($column, $direction);
            }

            return;
        }

        foreach ($this->orders as $order) {
            $query->orderBy($order['column'], $order['direction']);
        }
    }

    /**
     * Build and execute the query, returning paginated data or a collection.
     *
     * @return LengthAwarePaginator<int, EloquentModel>|Collection<int, EloquentModel>
     */
    public function buildData(): LengthAwarePaginator|Collection
    {
        $query = $this->newQuery();

        if (! empty($this->relations)) {
            $query->with($this->relations);
        }

        foreach ($this->queryCallbacks as $callback) {
            $callback($query);
        }

        if ($this->filter !== null) {
            if (is_callable($this->filter)) {
                ($this->filter)($query);
            } elseif (is_object($this->filter) && method_exists($this->filter, 'execute')) {
                call_user_func([$this->filter, 'execute'], $query);
            } elseif (is_object($this->filter) && method_exists($this->filter, 'apply')) {
                call_user_func([$this->filter, 'apply'], $query);
            }
        }

        $this->applySorting($query);

        if ($this->paginated) {
            $perPage = (int) request()->input($this->perPageName, $this->perPage);

            if ($perPage <= 0) {
                $perPage = $this->perPage;
            }

            /** @var LengthAwarePaginator<int, EloquentModel> $paginator */
            $paginator = $query->paginate($perPage, ['*'], $this->pageName);

            if (request()->query()) {
                $paginator->appends(request()->query());
            }

            return $paginator;
        }

        return $query->get();
    }
}
