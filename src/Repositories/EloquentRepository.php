<?php

declare(strict_types=1);

namespace BlatUI\Admin\Repositories;

use BlatUI\Admin\Contracts\Repository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EloquentRepository implements Repository
{
    /**
     * The underlying model or builder instance.
     *
     * @var Model|Builder<Model>
     */
    protected Model|Builder $model;

    /**
     * Create a new repository instance.
     *
     * @param  Model|Builder<Model>|class-string<Model>  $model
     */
    public function __construct(Model|Builder|string $model)
    {
        if (is_string($model)) {
            /** @var Model $model */
            $model = new $model;
        }

        $this->model = $model;
    }

    /**
     * Get the primary key name for the repository.
     */
    public function getKeyName(): string
    {
        return $this->getModel()->getKeyName();
    }

    /**
     * Get the created at column name.
     */
    public function getCreatedAtColumn(): ?string
    {
        $model = $this->getModel();

        return $model->usesTimestamps() ? $model->getCreatedAtColumn() : null;
    }

    /**
     * Get the updated at column name.
     */
    public function getUpdatedAtColumn(): ?string
    {
        $model = $this->getModel();

        return $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;
    }

    /**
     * Determine if the model uses soft deletes.
     */
    public function isSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->getModel()), true);
    }

    /**
     * Get the underlying model or query source.
     */
    public function model(): mixed
    {
        return $this->model;
    }

    /**
     * Find a record by its primary key for editing.
     */
    public function edit(mixed $key): mixed
    {
        return $this->newQuery()->find($key);
    }

    /**
     * Update a record with given values.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(mixed $key, array $values): bool
    {
        $model = $this->edit($key);

        if (! $model instanceof Model) {
            return false;
        }

        return (bool) $model->update($values);
    }

    /**
     * Delete record(s) by primary key.
     */
    public function destroy(mixed $key): bool
    {
        if ($key instanceof Arrayable) {
            $key = $key->toArray();
        }

        if (is_array($key)) {
            if ($key === []) {
                return false;
            }

            $models = $this->newQuery()->whereIn($this->getKeyName(), $key)->get();

            if ($models->isEmpty()) {
                return false;
            }

            foreach ($models as $model) {
                $model->delete();
            }

            return true;
        }

        $model = $this->edit($key);

        if (! $model instanceof Model) {
            return false;
        }

        return (bool) $model->delete();
    }

    /**
     * Get the underlying Eloquent model instance.
     */
    public function getModel(): Model
    {
        if ($this->model instanceof Builder) {
            return $this->model->getModel();
        }

        return $this->model;
    }

    /**
     * Get a new query builder instance for the model.
     *
     * @return Builder<Model>
     */
    public function newQuery(): Builder
    {
        if ($this->model instanceof Builder) {
            return clone $this->model;
        }

        return $this->model->newQuery();
    }
}
