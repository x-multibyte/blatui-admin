<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class In extends Field
{
    /**
     * Selectable options.
     *
     * @var array<string|int, string>
     */
    protected array $options = [];

    /**
     * Set selectable options.
     *
     * @param  array<string|int, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get selectable options.
     *
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get parsed values as an array.
     *
     * @return array<int, mixed>
     */
    public function getValues(): array
    {
        $raw = $this->getValue();

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn (mixed $v): bool => $v !== null && $v !== ''));
        }

        $items = explode(',', (string) $raw);

        return array_values(array_filter(array_map('trim', $items), fn (string $v): bool => $v !== ''));
    }

    /**
     * Check if active values exist.
     */
    public function hasValue(): bool
    {
        return ! empty($this->getValues());
    }

    /**
     * Apply in condition to query.
     *
     * @param  Builder<EloquentModel>  $query
     */
    public function apply(Builder $query): void
    {
        $values = $this->getValues();

        if (empty($values)) {
            return;
        }

        if (str_contains($this->column, '.')) {
            [$relation, $column] = explode('.', $this->column, 2);
            $query->whereHas($relation, function (Builder $q) use ($column, $values): void {
                $q->whereIn($column, $values);
            });
        } else {
            $query->whereIn($this->column, $values);
        }
    }

    /**
     * Render the in filter field.
     */
    public function render(): string
    {
        if (empty($this->options)) {
            return parent::render();
        }

        $id = htmlspecialchars($this->getId(), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->getName(), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($this->getLabel(), ENT_QUOTES, 'UTF-8');
        $selectedValues = array_map('strval', $this->getValues());

        $optionsHtml = '<option value="">All</option>';
        foreach ($this->options as $key => $optLabel) {
            $keyStr = (string) $key;
            $selected = in_array($keyStr, $selectedValues, true) ? ' selected' : '';
            $optionsHtml .= '<option value="'.htmlspecialchars($keyStr, ENT_QUOTES, 'UTF-8').'"'.$selected.'>'.htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8').'</option>';
        }

        return <<<HTML
<div class="flex flex-col gap-1.5">
    <label for="{$id}" class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {$label}
    </label>
    <div class="relative">
        <select
            id="{$id}"
            name="{$name}"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        >
            {$optionsHtml}
        </select>
    </div>
</div>
HTML;
    }
}
