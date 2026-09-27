<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use ReflectionFunction;
use Stringable;
use Throwable;

class Column
{
    /**
     * Column name or key.
     */
    protected string $name;

    /**
     * Column header label.
     */
    protected string $label;

    /**
     * Whether the column is sortable.
     */
    protected bool $sortable = false;

    /**
     * Column width.
     */
    protected string|int|null $width = null;

    /**
     * Tooltip or help text for header.
     */
    protected ?string $help = null;

    /**
     * Default value when cell data is null.
     */
    protected mixed $default = null;

    /**
     * Text alignment ('left', 'center', 'right').
     */
    protected string $align = 'left';

    /**
     * Display callbacks pipeline.
     *
     * @var array<int, Closure>
     */
    protected array $callbacks = [];

    /**
     * Create a new column instance.
     */
    public function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label ?? Str::headline(str_replace('.', ' ', $name));
    }

    /**
     * Get column name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get column label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set column label.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set column sortable state.
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * Check if column is sortable.
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * Set column width.
     */
    public function width(string|int $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Get column width.
     */
    public function getWidth(): string|int|null
    {
        return $this->width;
    }

    /**
     * Set column help text.
     */
    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    /**
     * Get column help text.
     */
    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * Check if column has help text.
     */
    public function hasHelp(): bool
    {
        return $this->help !== null && $this->help !== '';
    }

    /**
     * Set default fallback value.
     */
    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Get default fallback value.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Set column text alignment.
     */
    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    /**
     * Get column text alignment.
     */
    public function getAlign(): string
    {
        return $this->align;
    }

    /**
     * Add a display callback to the pipeline.
     */
    public function display(Closure $callback): static
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Get registered display callbacks.
     *
     * @return array<int, Closure>
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * Format cell as badge.
     *
     * @param  string|array<mixed, string>  $variant
     * @param  array<mixed, string>  $map
     */
    public function badge(string|array $variant = 'default', array $map = []): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column, $variant, $map): HtmlString {
            return new HtmlString((new Displayers\Badge($column, $value, $row))->display($variant, $map));
        });
    }

    /**
     * Format cell as hyperlink.
     */
    public function link(string|Closure|null $href = null, string $target = '_self'): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column, $href, $target): HtmlString {
            return new HtmlString((new Displayers\Link($column, $value, $row))->display($href, $target));
        });
    }

    /**
     * Format cell as copyable trigger.
     */
    public function copyable(): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column): HtmlString {
            return new HtmlString((new Displayers\Copyable($column, $value, $row))->display());
        });
    }

    /**
     * Format cell as image thumbnail.
     */
    public function image(?string $server = null, int $width = 32, int $height = 32): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column, $server, $width, $height): HtmlString {
            return new HtmlString((new Displayers\Image($column, $value, $row))->display($server, $width, $height));
        });
    }

    /**
     * Format cell as date/time.
     */
    public function datetime(string $format = 'Y-m-d H:i:s'): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column, $format): HtmlString {
            $text = (new Displayers\Datetime($column, $value, $row))->display($format);

            return new HtmlString(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        });
    }

    /**
     * Map dictionary values.
     *
     * @param  array<mixed, string>  $map
     */
    public function using(array $map, string $default = ''): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column, $map, $default): HtmlString {
            $text = (new Displayers\Using($column, $value, $row))->display($map, $default);

            return new HtmlString(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        });
    }

    /**
     * Truncate string value.
     */
    public function limit(int $limit = 30, string $end = '...'): static
    {
        $column = $this;

        return $this->display(static function (mixed $value, mixed $row = null) use ($column, $limit, $end): HtmlString {
            $text = (new Displayers\Limit($column, $value, $row))->display($limit, $end);

            return new HtmlString(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        });
    }

    /**
     * Render the cell value for a row.
     */
    public function renderCell(mixed $value, mixed $row = null): string
    {
        if ($value === null && $row !== null) {
            $value = data_get($row, $this->name);
        }

        if (($value === null || $value === '') && $this->default !== null) {
            $value = $this->default;
        }

        $current = $value;

        foreach ($this->callbacks as $callback) {
            $bound = $callback;

            if (is_object($row)) {
                $ref = new ReflectionFunction($callback);

                if (! $ref->isStatic()) {
                    try {
                        $bound = $callback->bindTo($row) ?? $callback;
                    } catch (Throwable) {
                        $bound = $callback;
                    }
                }
            }

            $current = $bound($current, $row);
        }

        if ($current === null) {
            return '';
        }

        // Htmlable/HtmlString instances are pre-escaped by displayers — pass through directly.
        if ($current instanceof Htmlable) {
            return $current->toHtml();
        }

        if (is_scalar($current)) {
            return htmlspecialchars((string) $current, ENT_QUOTES, 'UTF-8');
        }

        if ($current instanceof Stringable) {
            return htmlspecialchars((string) $current, ENT_QUOTES, 'UTF-8');
        }

        if (is_array($current)) {
            return htmlspecialchars(json_encode($current, JSON_UNESCAPED_UNICODE) ?: '', ENT_QUOTES, 'UTF-8');
        }

        if (is_object($current)) {
            if (method_exists($current, '__toString')) {
                return htmlspecialchars((string) $current, ENT_QUOTES, 'UTF-8');
            }

            return htmlspecialchars(json_encode($current, JSON_UNESCAPED_UNICODE) ?: '', ENT_QUOTES, 'UTF-8');
        }

        return '';
    }
}
