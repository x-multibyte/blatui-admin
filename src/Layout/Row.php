<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;

class Row implements Htmlable, Renderable
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
     * Render the row HTML.
     */
    public function render(): string
    {
        $escapedClass = htmlspecialchars($this->class, ENT_QUOTES, 'UTF-8');
        $html = "<div class=\"{$escapedClass}\">";

        foreach ($this->columns as $column) {
            $html .= $column->render();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render();
    }
}
