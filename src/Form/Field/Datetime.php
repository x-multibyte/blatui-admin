<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

use BlatUI\Admin\Form\Field;

class Datetime extends Field
{
    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.field.datetime';

    /**
     * Date/time format.
     */
    protected string $format = 'Y-m-d H:i:s';

    /**
     * Set date/time format.
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Get date/time format.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    public function defaultVariables(): array
    {
        return array_merge(parent::defaultVariables(), [
            'format' => $this->format,
        ]);
    }
}
