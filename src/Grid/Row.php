<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Grid\Actions\Delete;
use BlatUI\Admin\Grid\Actions\Edit;
use BlatUI\Admin\Grid\Actions\QuickEdit;
use BlatUI\Admin\Grid\Actions\Show;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * @implements Arrayable<string, mixed>
 */
class Row implements Arrayable
{
    /**
     * The row's underlying data.
     */
    protected mixed $data;

    /**
     * Zero-based row index/number.
     */
    protected int $number;

    /**
     * Resource URI prefix.
     */
    protected ?string $resource;

    /**
     * Primary key column name.
     */
    protected string $keyName = 'id';

    /**
     * Whether all row actions are disabled.
     */
    protected bool $actionsDisabled = false;

    /**
     * Whether Show action is disabled.
     */
    protected bool $showDisabled = false;

    /**
     * Whether Edit action is disabled.
     */
    protected bool $editDisabled = false;

    /**
     * Whether Delete action is disabled.
     */
    protected bool $deleteDisabled = false;

    /**
     * Whether QuickEdit action is disabled.
     */
    protected bool $quickEditDisabled = true;

    /**
     * Default row actions dictionary.
     *
     * @var array<string, RowAction>
     */
    protected array $defaultActions = [];

    /**
     * Whether default actions have been initialized.
     */
    protected bool $defaultActionsAdded = false;

    /**
     * Prepended actions.
     *
     * @var array<int, mixed>
     */
    protected array $prepends = [];

    /**
     * Appended actions.
     *
     * @var array<int, mixed>
     */
    protected array $appends = [];

    /**
     * Create a new Row instance.
     */
    public function __construct(mixed $data, int $number = 0, ?string $resource = null)
    {
        $this->data = $data;
        $this->number = $number;
        $this->resource = $resource;
    }

    /**
     * Resolve the primary key of the row data.
     */
    public function getKey(): mixed
    {
        if ($this->data instanceof Model) {
            return $this->data->getKey();
        }

        if (is_array($this->data)) {
            return $this->data[$this->keyName] ?? $this->data['id'] ?? null;
        }

        if (is_object($this->data)) {
            $key = $this->keyName;

            return $this->data->{$key} ?? $this->data->id ?? null;
        }

        return null;
    }

    /**
     * Get underlying data.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Alias of getData().
     */
    public function model(): mixed
    {
        return $this->data;
    }

    /**
     * Set row data.
     */
    public function setData(mixed $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get row number/index.
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * Set row number/index.
     */
    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get resource path.
     */
    public function getResource(): ?string
    {
        if ($this->resource !== null) {
            return $this->resource;
        }

        if ($this->data instanceof Model) {
            return Admin::url($this->data->getTable());
        }

        if (function_exists('request') && ($path = trim((string) request()->path(), '/')) !== '') {
            return '/'.$path;
        }

        return null;
    }

    /**
     * Set resource path.
     */
    public function setResource(?string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Set primary key column name.
     */
    public function setKeyName(string $name): static
    {
        $this->keyName = $name;

        return $this;
    }

    /**
     * Get primary key column name.
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }

    /**
     * Configure row actions via callback.
     */
    public function actions(?Closure $callback = null): static
    {
        if ($callback instanceof Closure) {
            $callback->call($this, $this);
        }

        return $this;
    }

    /**
     * Disable all row actions.
     */
    public function disableActions(bool $disable = true): static
    {
        $this->actionsDisabled = $disable;

        return $this;
    }

    /**
     * Check if actions are disabled.
     */
    public function isActionsDisabled(): bool
    {
        return $this->actionsDisabled;
    }

    /**
     * Disable or enable Show action.
     */
    public function disableShow(bool $disable = true): static
    {
        $this->showDisabled = $disable;

        return $this;
    }

    /**
     * Enable or disable Show action.
     */
    public function show(bool $enable = true): static
    {
        $this->showDisabled = ! $enable;

        return $this;
    }

    /**
     * Disable or enable Edit action.
     */
    public function disableEdit(bool $disable = true): static
    {
        $this->editDisabled = $disable;

        return $this;
    }

    /**
     * Enable or disable Edit action.
     */
    public function edit(bool $enable = true): static
    {
        $this->editDisabled = ! $enable;

        return $this;
    }

    /**
     * Disable or enable Delete action.
     */
    public function disableDelete(bool $disable = true): static
    {
        $this->deleteDisabled = $disable;

        return $this;
    }

    /**
     * Enable or disable Delete action.
     */
    public function delete(bool $enable = true): static
    {
        $this->deleteDisabled = ! $enable;

        return $this;
    }

    /**
     * Disable or enable QuickEdit action.
     */
    public function disableQuickEdit(bool $disable = true): static
    {
        $this->quickEditDisabled = $disable;

        return $this;
    }

    /**
     * Enable or disable QuickEdit action.
     */
    public function quickEdit(bool $enable = true): static
    {
        $this->quickEditDisabled = ! $enable;

        return $this;
    }

    /**
     * Initialize default row actions.
     */
    public function addDefaultActions(): static
    {
        $this->defaultActions = [
            'show' => new Show,
            'edit' => new Edit,
            'quickEdit' => new QuickEdit,
            'delete' => new Delete,
        ];
        $this->defaultActionsAdded = true;

        return $this;
    }

    /**
     * Get a specific default action by name.
     */
    public function getAction(string $name): ?RowAction
    {
        if (! $this->defaultActionsAdded) {
            $this->addDefaultActions();
        }

        return $this->defaultActions[$name] ?? null;
    }

    /**
     * Set or replace a default action by name.
     */
    public function setAction(string $name, RowAction $action): static
    {
        if (! $this->defaultActionsAdded) {
            $this->addDefaultActions();
        }

        $this->defaultActions[$name] = $action;

        return $this;
    }

    /**
     * Append a custom action.
     */
    public function append(mixed $action): static
    {
        $this->appends[] = $action;

        return $this;
    }

    /**
     * Prepend a custom action.
     */
    public function prepend(mixed $action): static
    {
        array_unshift($this->prepends, $action);

        return $this;
    }

    /**
     * Add a custom action (appends to list).
     */
    public function addAction(mixed $action): static
    {
        return $this->append($action);
    }

    /**
     * Get default actions map.
     *
     * @return array<string, RowAction>
     */
    public function getDefaultActions(): array
    {
        if (! $this->defaultActionsAdded) {
            $this->addDefaultActions();
        }

        return $this->defaultActions;
    }

    public function isShowDisabled(): bool
    {
        return $this->showDisabled;
    }

    public function isEditDisabled(): bool
    {
        return $this->editDisabled;
    }

    public function isDeleteDisabled(): bool
    {
        return $this->deleteDisabled;
    }

    public function isQuickEditDisabled(): bool
    {
        return $this->quickEditDisabled;
    }

    /**
     * @return array<int, mixed>
     */
    public function getActions(): array
    {
        if (! $this->defaultActionsAdded) {
            $this->addDefaultActions();
        }

        $items = [];

        foreach ($this->prepends as $action) {
            $items[] = $action;
        }

        if (! $this->showDisabled && isset($this->defaultActions['show'])) {
            $items[] = $this->defaultActions['show'];
        }

        if (! $this->editDisabled && isset($this->defaultActions['edit'])) {
            $items[] = $this->defaultActions['edit'];
        }

        if (! $this->quickEditDisabled && isset($this->defaultActions['quickEdit'])) {
            $items[] = $this->defaultActions['quickEdit'];
        }

        if (! $this->deleteDisabled && isset($this->defaultActions['delete'])) {
            $items[] = $this->defaultActions['delete'];
        }

        foreach ($this->appends as $action) {
            $items[] = $action;
        }

        return $items;
    }

    /**
     * Render a specific Column cell for this row.
     */
    public function cell(Column $column): HtmlString
    {
        $value = data_get($this->data, $column->getName());

        return new HtmlString($column->renderCell($value, $this->data));
    }

    /**
     * Retrieve column data using data_get.
     */
    public function column(string $name): mixed
    {
        return data_get($this->data, $name);
    }

    /**
     * Magic getter for row fields.
     */
    public function __get(string $name): mixed
    {
        return data_get($this->data, $name);
    }

    /**
     * Convert row data to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->data instanceof Arrayable) {
            /** @var array<string, mixed> $array */
            $array = $this->data->toArray();

            return $array;
        }

        if (is_array($this->data)) {
            return $this->data;
        }

        /** @var array<string, mixed> $array */
        $array = (array) $this->data;

        return $array;
    }
}
