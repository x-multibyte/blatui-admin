<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

class Multiselect extends Relation
{
    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.field.multiselect';

    /**
     * Checkbox key-value options.
     *
     * @var array<int|string, string>
     */
    protected array $options = [];

    /**
     * Indicates whether the search box and select-all control are rendered.
     */
    protected bool $searchable = true;

    /**
     * Set the options array.
     *
     * @param  array<int|string, string>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the options array.
     *
     * @return array<int|string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set whether the field renders its search box and select-all control.
     */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Check whether the field renders its search box and select-all control.
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Get default variables for the Blade view.
     *
     * The parent implementation coerces the value to a string, which would
     * collapse a multi-value selection into its first entry. The raw value is
     * restored as a list of strings instead: submitted form values and Eloquent
     * pivot keys are both string-typed, so the template can compare them with
     * a strict `in_array()` and stay honest about what it matched.
     *
     * @return array<string, mixed>
     */
    public function defaultVariables(): array
    {
        $variables = parent::defaultVariables();

        $rawValue = $this->getValue() ?? $this->defaultValue;

        $variables['value'] = is_array($rawValue)
            ? array_values(array_map('strval', $rawValue))
            : (is_scalar($rawValue) ? [(string) $rawValue] : []);

        $variables['options'] = $this->options;
        $variables['searchable'] = $this->searchable;

        return $variables;
    }
}
