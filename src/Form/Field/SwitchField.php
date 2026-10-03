<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

use BlatUI\Admin\Form\Field;

class SwitchField extends Field
{
    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.field.switch';

    /**
     * Switch state labels.
     *
     * @var array<int|string, string>
     */
    protected array $states = [
        1 => 'ON',
        0 => 'OFF',
    ];

    /**
     * Set state labels.
     *
     * @param  array<int|string, string>  $states
     */
    public function states(array $states): static
    {
        $this->states = $states;

        return $this;
    }

    /**
     * Get state labels.
     *
     * @return array<int|string, string>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    public function defaultVariables(): array
    {
        $rawVal = $this->getValue() ?? $this->defaultValue;

        return array_merge(parent::defaultVariables(), [
            'value' => (int) (bool) $rawVal,
            'states' => $this->states,
        ]);
    }
}
