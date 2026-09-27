<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use BlatUI\Admin\Grid\Column;

abstract class AbstractDisplayer
{
    /**
     * Create a new displayer instance.
     */
    public function __construct(
        protected Column $column,
        protected mixed $value,
        protected mixed $row = null,
    ) {}

    /**
     * Get the column instance.
     */
    public function getColumn(): Column
    {
        return $this->column;
    }

    /**
     * Get the cell value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Get the row instance or data.
     */
    public function getRow(): mixed
    {
        return $this->row;
    }

    /**
     * Render the displayer output.
     */
    abstract public function display(): string;
}
