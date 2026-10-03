<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

use BlatUI\Admin\Form\Field;

class Select extends Field
{
    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.field.select';

    /**
     * Dropdown key-value options.
     *
     * @var array<int|string, mixed>
     */
    protected array $options = [];

    /**
     * Set the options array.
     *
     * @param  array<int|string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the options array.
     *
     * @return array<int|string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    public function defaultVariables(): array
    {
        return array_merge(parent::defaultVariables(), [
            'options' => $this->options,
        ]);
    }
}
