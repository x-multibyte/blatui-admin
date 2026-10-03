<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class In extends Field
{
    /**
     * View template name.
     */
    protected string $view = 'blatui-admin::grid.filter.select';

    /**
     * Fallback view template name when options are empty.
     */
    protected string $fallbackView = 'blatui-admin::grid.filter.text';

    /**
     * Selectable options.
     *
     * @var array<string|int, string>
     */
    protected array $options = [];

    /**
     * Set or get custom fallback view template.
     */
    public function fallbackView(?string $view = null): static|string
    {
        if ($view === null) {
            return $this->fallbackView;
        }

        $this->fallbackView = $view;

        return $this;
    }

    /**
     * Get fallback view template name.
     */
    public function getFallbackView(): string
    {
        return $this->fallbackView;
    }

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
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    protected function defaultVariables(): array
    {
        return [
            'field' => $this->newProxy(),
            'id' => $this->getId(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'options' => $this->options,
            'selectedValues' => array_map('strval', $this->getValues()),
        ];
    }

    /**
     * Render the in filter field.
     */
    public function render(): string
    {
        $view = empty($this->options) ? $this->getFallbackView() : $this->view;

        if (function_exists('view') && view()->exists($view)) {
            $vars = empty($this->options) ? parent::defaultVariables() : $this->defaultVariables();

            return view($view, $vars)->render();
        }

        return '';
    }
}
