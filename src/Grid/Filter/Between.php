<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Between extends Field
{
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
     * Render the between input fields.
     */
    public function render(): string
    {
        $id = htmlspecialchars($this->getId(), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($this->getLabel(), ENT_QUOTES, 'UTF-8');
        $range = $this->getRangeValue();
        $startVal = is_scalar($range['start']) ? htmlspecialchars((string) $range['start'], ENT_QUOTES, 'UTF-8') : '';
        $endVal = is_scalar($range['end']) ? htmlspecialchars((string) $range['end'], ENT_QUOTES, 'UTF-8') : '';

        return <<<HTML
<div class="flex flex-col gap-1.5">
    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {$label}
    </label>
    <div class="flex items-center gap-1.5">
        <input
            id="{$id}_start"
            type="text"
            name="{$name}[start]"
            value="{$startVal}"
            placeholder="From"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
        <span class="text-xs text-gray-400 shrink-0">—</span>
        <input
            id="{$id}_end"
            type="text"
            name="{$name}[end]"
            value="{$endVal}"
            placeholder="To"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
    </div>
</div>
HTML;
    }
}
