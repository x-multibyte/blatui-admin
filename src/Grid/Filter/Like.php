<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Like extends Field
{
    /**
     * Apply like condition to query.
     *
     * @param  Builder<EloquentModel>  $query
     */
    public function apply(Builder $query): void
    {
        $value = $this->getValue();

        if ($value === null || $value === '') {
            return;
        }

        $pattern = "%{$value}%";

        if (str_contains($this->column, '.')) {
            [$relation, $column] = explode('.', $this->column, 2);
            $query->whereHas($relation, function (Builder $q) use ($column, $pattern): void {
                $q->where($column, 'like', $pattern);
            });
        } else {
            $query->where($this->column, 'like', $pattern);
        }
    }
}
