<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

use BlatUI\Admin\Form\Field;

class Textarea extends Field
{
    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.field.textarea';

    /**
     * Number of textarea rows.
     */
    protected int $rows = 4;

    /**
     * Set the number of rows.
     */
    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Get the number of rows.
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    public function defaultVariables(): array
    {
        return array_merge(parent::defaultVariables(), [
            'rows' => $this->rows,
        ]);
    }
}
