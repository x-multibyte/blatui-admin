<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Where extends Field
{
    /**
     * Query customization callback.
     *
     * @var Closure(Builder<EloquentModel>, mixed): void
     */
    protected Closure $callback;

    /**
     * Create a new custom Where filter instance.
     *
     * @param  Closure(Builder<EloquentModel>, mixed): void  $callback
     */
    public function __construct(Closure $callback, ?string $label = null, ?string $column = null)
    {
        $col = $column ?? 'custom_'.spl_object_id($callback);
        parent::__construct($col, $label);
        $this->callback = $callback;
    }

    /**
     * Apply custom where condition to query.
     *
     * @param  Builder<EloquentModel>  $query
     */
    public function apply(Builder $query): void
    {
        if (! $this->hasValue()) {
            return;
        }

        ($this->callback)($query, $this->getValue());
    }
}
