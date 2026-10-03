<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Filter;

use BlatUI\Admin\Grid\Filter;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

abstract class Field implements Htmlable, Renderable, Stringable
{
    /**
     * Database column name.
     */
    protected string $column;

    /**
     * Field display label.
     */
    protected string $label;

    /**
     * Input name attribute.
     */
    protected string $name;

    /**
     * Field value.
     */
    protected mixed $value = null;

    /**
     * Default fallback value.
     */
    protected mixed $defaultValue = null;

    /**
     * Input placeholder.
     */
    protected ?string $placeholder = null;

    /**
     * Parent filter instance.
     */
    protected ?Filter $filter = null;

    /**
     * Custom HTML attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Unique HTML element ID.
     */
    protected ?string $id = null;

    /**
     * View template name.
     */
    protected string $view = 'blatui-admin::grid.filter.text';

    /**
     * Create a new filter field instance.
     */
    public function __construct(string $column, ?string $label = null)
    {
        $this->column = $column;
        $this->label = $label ?? Str::headline(str_replace('.', ' ', $column));
        $this->name = $column;
    }

    /**
     * Get the database column.
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get or set the display label.
     */
    public function label(?string $label = null): static|string
    {
        if ($label === null) {
            return $this->label;
        }

        $this->label = $label;

        return $this;
    }

    /**
     * Get the display label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get or set the input name attribute.
     */
    public function name(?string $name = null): static|string
    {
        if ($name === null) {
            return $this->name;
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Get the input name attribute.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get or set the placeholder text.
     */
    public function placeholder(?string $placeholder = null): static|string|null
    {
        if (func_num_args() === 0) {
            return $this->placeholder;
        }

        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Get the placeholder text.
     */
    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Get or set the default value.
     */
    public function default(mixed $value = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->defaultValue;
        }

        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Get the default value.
     */
    public function getDefault(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Get or set explicit value.
     */
    public function value(mixed $value = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->getValue();
        }

        $this->value = $value;

        return $this;
    }

    /**
     * Resolve the active value from request or default.
     */
    public function getValue(): mixed
    {
        if ($this->value !== null) {
            return $this->value;
        }

        $request = request();
        $input = $request->input($this->name);

        if ($input === null) {
            $input = $request->query($this->name);
        }

        if ($input !== null && $input !== '') {
            return $input;
        }

        return $this->defaultValue;
    }

    /**
     * Check if the field has an active value.
     */
    public function hasValue(): bool
    {
        $val = $this->getValue();

        if ($val === null || $val === '') {
            return false;
        }

        if (is_array($val)) {
            return ! empty(array_filter($val, fn (mixed $v): bool => $v !== null && $v !== ''));
        }

        return true;
    }

    /**
     * Get or generate the element ID.
     */
    public function getId(): string
    {
        if ($this->id === null) {
            $this->id = 'filter_'.str_replace(['[', ']', '.', ' '], '_', $this->name).'_'.spl_object_id($this);
        }

        return $this->id;
    }

    /**
     * Set the element ID.
     */
    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the parent filter instance.
     */
    public function setFilter(Filter $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Get the parent filter instance.
     */
    public function getFilter(): ?Filter
    {
        return $this->filter;
    }

    /**
     * Add or set HTML attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Apply filter condition to the query builder.
     *
     * @param  Builder<EloquentModel>  $query
     */
    abstract public function apply(Builder $query): void;

    /**
     * Apply simple condition to query, handling relation dot-notation if present.
     *
     * @param  Builder<EloquentModel>  $query
     */
    protected function applySimpleCondition(Builder $query, string $operator, mixed $value): void
    {
        if (str_contains($this->column, '.')) {
            [$relation, $column] = explode('.', $this->column, 2);
            $query->whereHas($relation, function (Builder $q) use ($column, $operator, $value): void {
                $q->where($column, $operator, $value);
            });
        } else {
            $query->where($this->column, $operator, $value);
        }
    }

    /**
     * Set or get custom view template.
     */
    public function view(?string $view = null): static|string
    {
        if ($view === null) {
            return $this->view;
        }

        $this->view = $view;

        return $this;
    }

    /**
     * Get view template name.
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Create a safe proxy wrapper to prevent recursive view evaluation.
     */
    protected function newProxy(): object
    {
        return new class($this)
        {
            public function __construct(protected Field $field) {}

            /**
             * @param  array<int, mixed>  $args
             */
            public function __call(string $method, array $args): mixed
            {
                return $this->field->{$method}(...$args);
            }

            public function __get(string $name): mixed
            {
                $getter = 'get'.ucfirst($name);

                if (method_exists($this->field, $getter)) {
                    return $this->field->{$getter}();
                }

                throw new InvalidArgumentException("Undefined property or getter for '{$name}' on Field proxy.");
            }
        };
    }

    /**
     * Get default variables for the Blade view.
     *
     * @return array<string, mixed>
     */
    protected function defaultVariables(): array
    {
        $rawVal = $this->getValue();

        return [
            'field' => $this->newProxy(),
            'id' => $this->getId(),
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'value' => is_scalar($rawVal) ? (string) $rawVal : '',
            'placeholder' => $this->getPlaceholder() ?? $this->getLabel(),
        ];
    }

    /**
     * Render the field input HTML.
     */
    public function render(): string
    {
        if (function_exists('view') && view()->exists($this->view)) {
            return view($this->view, $this->defaultVariables())->render();
        }

        return '';
    }

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
