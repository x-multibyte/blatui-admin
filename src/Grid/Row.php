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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Stringable;

/**
 * @implements Arrayable<string, mixed>
 */
class Row implements Arrayable, Htmlable, Renderable, Stringable
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

    /**
     * Render the combined row actions HTML.
     */
    public function renderActions(): string
    {
        if ($this->actionsDisabled) {
            return '';
        }

        if (! $this->defaultActionsAdded) {
            $this->addDefaultActions();
        }

        $items = [];

        foreach ($this->prepends as $action) {
            $rendered = $this->renderAction($action);

            if ($rendered !== '') {
                $items[] = $rendered;
            }
        }

        if (! $this->showDisabled && isset($this->defaultActions['show'])) {
            $rendered = $this->renderAction($this->defaultActions['show']);

            if ($rendered !== '') {
                $items[] = $rendered;
            }
        }

        if (! $this->editDisabled && isset($this->defaultActions['edit'])) {
            $rendered = $this->renderAction($this->defaultActions['edit']);

            if ($rendered !== '') {
                $items[] = $rendered;
            }
        }

        if (! $this->quickEditDisabled && isset($this->defaultActions['quickEdit'])) {
            $rendered = $this->renderAction($this->defaultActions['quickEdit']);

            if ($rendered !== '') {
                $items[] = $rendered;
            }
        }

        if (! $this->deleteDisabled && isset($this->defaultActions['delete'])) {
            $rendered = $this->renderAction($this->defaultActions['delete']);

            if ($rendered !== '') {
                $items[] = $rendered;
            }
        }

        foreach ($this->appends as $action) {
            $rendered = $this->renderAction($action);

            if ($rendered !== '') {
                $items[] = $rendered;
            }
        }

        if (empty($items)) {
            return '';
        }

        return '<div class="inline-flex items-center gap-3">'.implode('', $items).'</div>';
    }

    /**
     * Helper to render an action element.
     */
    protected function renderAction(mixed $action): string
    {
        if ($action instanceof RowAction) {
            return $action->render($this);
        }

        if ($action instanceof Renderable) {
            return (string) $action->render();
        }

        if ($action instanceof Htmlable) {
            return $action->toHtml();
        }

        if ($action instanceof Closure) {
            return (string) $action($this);
        }

        return (string) $action;
    }

    /**
     * Render the row selection checkbox.
     */
    public function renderCheckbox(string $name = '_row_id'): string
    {
        $key = htmlspecialchars((string) ($this->getKey() ?? $this->number), ENT_QUOTES, 'UTF-8');
        $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<input type="checkbox" class="grid-row-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 cursor-pointer" name="%s[]" value="%s" data-id="%s" x-model="selectedRows" />',
            $escapedName,
            $key,
            $key,
        );
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
     * Render the row HTML representation.
     */
    public function render(): string
    {
        return (string) $this->renderActions();
    }

    /**
     * Get content as a string of HTML.
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Convert row to string representation.
     */
    public function __toString(): string
    {
        return $this->toHtml();
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
