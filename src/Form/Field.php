<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form;

use BlatUI\Admin\Form;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use InvalidArgumentException;
use Stringable;

abstract class Field implements Htmlable, Renderable, Stringable
{
    /**
     * Database column or field name.
     */
    protected string $column;

    /**
     * Field display label.
     */
    protected string $label;

    /**
     * Field value.
     */
    protected mixed $value = null;

    /**
     * Fallback default value.
     */
    protected mixed $defaultValue = null;

    /**
     * Help text displayed under field.
     */
    protected ?string $help = null;

    /**
     * Input placeholder text.
     */
    protected ?string $placeholder = null;

    /**
     * Validation rules.
     *
     * @var array<int|string, mixed>|string
     */
    protected array|string $rules = [];

    /**
     * Custom validation rule messages.
     *
     * @var array<string, string>
     */
    protected array $rulesMessages = [];

    /**
     * Indicates whether field is required.
     */
    protected bool $required = false;

    /**
     * Custom HTML element attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Parent Form instance.
     */
    protected ?Form $form = null;

    /**
     * Blade view template path.
     */
    protected string $view = '';

    /**
     * Create a new form field instance.
     */
    public function __construct(string $column, ?string $label = null)
    {
        $this->column = $column;
        $this->label = $label ?? ucfirst(str_replace(['.', '_'], ' ', $column));
    }

    /**
     * Get the database column name.
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get the field display label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Set the field display label.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the current field value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set the current field value.
     */
    public function value(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the fallback default value.
     */
    public function getDefault(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Set the fallback default value.
     */
    public function default(mixed $default): static
    {
        $this->defaultValue = $default;

        return $this;
    }

    /**
     * Get the input placeholder.
     */
    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Set the input placeholder.
     */
    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Get the field help text.
     */
    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * Set the field help text.
     */
    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    /**
     * Set validation rules and optional custom messages.
     *
     * @param  array<int|string, mixed>|string  $rules
     * @param  array<string, string>  $messages
     */
    public function rules(array|string $rules, array $messages = []): static
    {
        $this->rules = $rules;
        $this->rulesMessages = $messages;

        if (is_array($rules) && in_array('required', $rules, true)) {
            $this->required = true;
        } elseif (is_string($rules) && in_array('required', explode('|', $rules), true)) {
            $this->required = true;
        }

        return $this;
    }

    /**
     * Get the validation rules.
     *
     * @return array<int|string, mixed>|string
     */
    public function getRules(): array|string
    {
        if ($this->required) {
            if (is_array($this->rules)) {
                if (! in_array('required', $this->rules, true)) {
                    return array_merge(['required'], $this->rules);
                }

                return $this->rules;
            }

            if ($this->rules !== '') {
                $parts = explode('|', $this->rules);

                if (! in_array('required', $parts, true)) {
                    return 'required|'.$this->rules;
                }

                return $this->rules;
            }

            return ['required'];
        }

        return $this->rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return $this->rulesMessages;
    }

    /**
     * Set whether the field is required.
     */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Check if the field is required.
     */
    public function isRequired(): bool
    {
        return $this->required;
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
     * Get custom HTML attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set custom Blade view template.
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Get Blade view template path.
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Set parent Form instance.
     */
    public function setForm(Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    /**
     * Get parent Form instance.
     */
    public function getForm(): ?Form
    {
        return $this->form;
    }

    /**
     * Create an anti-recursion proxy wrapper for safe template evaluation.
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

                $isser = 'is'.ucfirst($name);

                if (method_exists($this->field, $isser)) {
                    return $this->field->{$isser}();
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
    public function defaultVariables(): array
    {
        $rawVal = $this->getValue() ?? $this->defaultValue;
        $val = '';

        if (is_scalar($rawVal) || $rawVal instanceof Stringable) {
            $val = (string) $rawVal;
        }

        return [
            'field' => $this->newProxy(),
            'id' => 'field_'.$this->getColumn(),
            'name' => $this->getColumn(),
            'label' => $this->getLabel(),
            'value' => $val,
            'placeholder' => $this->getPlaceholder() ?? $this->getLabel(),
            'help' => $this->getHelp(),
            'required' => $this->isRequired(),
        ];
    }

    /**
     * Render the field HTML string.
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
