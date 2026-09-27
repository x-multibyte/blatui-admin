<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

class Row
{
    /**
     * Columns contained in this row.
     *
     * @var array<int, Column>
     */
    protected array $columns = [];

    /**
     * Custom CSS class names.
     */
    protected string $class = 'grid grid-cols-12 gap-4 mb-4';

    /**
     * Row constructor.
     */
    public function __construct(mixed $content = '')
    {
        if (! empty($content)) {
            if ($content instanceof Column) {
                $this->addColumn($content);
            } else {
                $this->column(12, $content);
            }
        }
    }

    /**
     * Add a column to this row.
     *
     * @param  int|float|array<string, int>  $width
     */
    public function column(int|float|array $width, mixed $content): static
    {
        $column = new Column($content, $width);

        return $this->addColumn($column);
    }

    /**
     * Add a Column instance to the row.
     */
    public function addColumn(Column $column): static
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * Set custom CSS classes for the row grid container.
     */
    public function class(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Get the columns in this row.
     */
    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the CSS classes for this row.
     */
    public function getClass(): string
    {
        return $this->class;
    }
}
