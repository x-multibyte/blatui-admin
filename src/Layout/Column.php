<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

use BlatUI\Admin\Support\Helper;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;

class Column implements Htmlable, Renderable
{
    /**
     * Column width configuration.
     *
     * @var array<string, int>
     */
    protected array $width = [];

    /**
     * Column content items.
     *
     * @var array<int, mixed>
     */
    protected array $contents = [];

    /**
     * Column constructor.
     *
     * @param  int|float|array<string, int>  $width
     */
    public function __construct(mixed $content = '', int|float|array $width = 12)
    {
        if (is_array($width)) {
            $this->width = $width;
        } else {
            $normalized = $this->normalizeWidth($width);
            $this->width = ['md' => $normalized];
        }

        if ($content instanceof Closure) {
            $content($this);
        } elseif ($content !== '' && $content !== null) {
            $this->append($content);
        }
    }

    /**
     * Normalize the column width to a 1-12 scale.
     */
    protected function normalizeWidth(int|float $width): int
    {
        if ($width > 0 && $width < 1) {
            return (int) round(12 * $width);
        }

        return max(1, min(12, (int) $width));
    }

    /**
     * Append content to column.
     */
    public function append(mixed $content): static
    {
        $this->contents[] = $content;

        return $this;
    }

    /**
     * Add a nested row to this column.
     */
    public function row(mixed $content): Column
    {
        if ($content instanceof Closure) {
            $row = new Row;
            $content($row);
        } else {
            $row = new Row($content);
        }

        $this->append($row);

        return $this;
    }

    /**
     * Build the Tailwind CSS grid column classes.
     */
    protected function buildClasses(): string
    {
        $classes = ['col-span-12'];

        foreach ($this->width as $breakpoint => $span) {
            if ($span === 12 && $breakpoint === 'md') {
                continue;
            }
            $classes[] = "{$breakpoint}:col-span-{$span}";
        }

        return implode(' ', array_unique($classes));
    }

    /**
     * Render the column HTML.
     */
    public function render(): string
    {
        $classString = $this->buildClasses();
        $html = "<div class=\"{$classString}\">";

        foreach ($this->contents as $content) {
            $html .= Helper::render($content);
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
