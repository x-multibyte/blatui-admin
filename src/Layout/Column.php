<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

use Closure;

class Column
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
     * Get the column contents.
     */
    /**
     * @return array<int, mixed>
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * Get the CSS classes for this column.
     */
    public function getClass(): string
    {
        $classes = ['col-span-12'];

        foreach ($this->width as $breakpoint => $span) {
            if ($span === 12 && $breakpoint === 'md') {
                continue;
            }
            $escapedBreakpoint = htmlspecialchars((string) $breakpoint, ENT_QUOTES, 'UTF-8');
            $classes[] = "{$escapedBreakpoint}:col-span-{$span}";
        }

        return implode(' ', array_unique($classes));
    }
}
