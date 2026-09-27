<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Gt extends Field
{
    /**
     * Apply greater-than condition to query.
     *
     * @param  Builder<EloquentModel>  $query
     */
    public function apply(Builder $query): void
    {
        $value = $this->getValue();

        if ($value === null || $value === '') {
            return;
        }

        $this->applySimpleCondition($query, '>', $value);
    }
}
