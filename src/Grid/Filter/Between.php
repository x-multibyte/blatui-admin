<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Between extends Field
{
    /**
     * View template name.
     */
    protected string $view = 'blatui-admin::grid.filter.between';

    /**
     * Resolve start and end values.
     *
     * @return array{start: mixed, end: mixed}
     */
    public function getRangeValue(): array
    {
        $val = $this->getValue();

        $start = null;
        $end = null;

        if (is_array($val)) {
            $start = $val['start'] ?? $val[0] ?? null;
            $end = $val['end'] ?? $val[1] ?? null;
        }

        if ($start === null || $start === '') {
            $start = request()->input($this->name.'_start') ?? request()->input($this->name.'[start]');
        }

        if ($end === null || $end === '') {
            $end = request()->input($this->name.'_end') ?? request()->input($this->name.'[end]');
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Check if start or end has value.
     */
    public function hasValue(): bool
    {
        $range = $this->getRangeValue();

        return ($range['start'] !== null && $range['start'] !== '') ||
               ($range['end'] !== null && $range['end'] !== '');
    }

    /**
     * Apply between condition to query.
     *
     * @param  Builder<EloquentModel>  $query
     */
    public function apply(Builder $query): void
    {
        $range = $this->getRangeValue();
        $start = $range['start'];
        $end = $range['end'];

        $hasStart = $start !== null && $start !== '';
        $hasEnd = $end !== null && $end !== '';

        if (! $hasStart && ! $hasEnd) {
            return;
        }

        $applyClause = function (Builder $q) use ($start, $end, $hasStart, $hasEnd): void {
            if ($hasStart && $hasEnd) {
                $q->whereBetween($this->column, [$start, $end]);
            } elseif ($hasStart) {
                $q->where($this->column, '>=', $start);
            } elseif ($hasEnd) {
                $q->where($this->column, '<=', $end);
            }
        };

        if (str_contains($this->column, '.')) {
            [$relation, $column] = explode('.', $this->column, 2);
            $query->whereHas($relation, function (Builder $q) use ($column, $start, $end, $hasStart, $hasEnd): void {
                if ($hasStart && $hasEnd) {
                    $q->whereBetween($column, [$start, $end]);
                } elseif ($hasStart) {
                    $q->where($column, '>=', $start);
                } elseif ($hasEnd) {
                    $q->where($column, '<=', $end);
                }
            });
        } else {
            $applyClause($query);
        }
    }

    /**
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    protected function defaultVariables(): array
    {
        $range = $this->getRangeValue();

        return [
            'field' => $this->newProxy(),
            'id' => $this->getId(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'startVal' => is_scalar($range['start']) ? (string) $range['start'] : '',
            'endVal' => is_scalar($range['end']) ? (string) $range['end'] : '',
        ];
    }

    /**
     * Render the between input fields.
     */
    public function render(): string
    {
        if (function_exists('view') && view()->exists($this->view)) {
            return view($this->view, $this->defaultVariables())->render();
        }

        return '';
    }
}
