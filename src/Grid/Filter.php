<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use BlatUI\Admin\Grid\Filter\Between;
use BlatUI\Admin\Grid\Filter\Equal;
use BlatUI\Admin\Grid\Filter\Field;
use BlatUI\Admin\Grid\Filter\Gt;
use BlatUI\Admin\Grid\Filter\Gte;
use BlatUI\Admin\Grid\Filter\In;
use BlatUI\Admin\Grid\Filter\Like;
use BlatUI\Admin\Grid\Filter\Lt;
use BlatUI\Admin\Grid\Filter\Lte;
use BlatUI\Admin\Grid\Filter\Where;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Stringable;

class Filter implements Htmlable, Renderable, Stringable
{
    /**
     * The model instance or repository coordinator.
     */
    protected mixed $model;

    /**
     * Registered filter fields.
     *
     * @var array<int, Field>
     */
    protected array $fields = [];

    /**
     * Explicit expansion state override.
     */
    protected ?bool $expanded = null;

    /**
     * Form action URL.
     */
    protected ?string $action = null;

    /**
     * Unique form element ID.
     */
    protected ?string $id = null;

    /**
     * Custom view template.
     */
    protected string $view = 'blatui-admin::grid.filter';

    /**
     * Create a new Filter instance.
     */
    public function __construct(mixed $model = null)
    {
        $this->model = $model;
    }

    /**
     * Get underlying model.
     */
    public function getModel(): mixed
    {
        return $this->model;
    }

    /**
     * Add an Equal filter.
     */
    public function equal(string $column, ?string $label = null): Equal
    {
        $field = new Equal($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a Like filter.
     */
    public function like(string $column, ?string $label = null): Like
    {
        $field = new Like($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a Greater-Than filter.
     */
    public function gt(string $column, ?string $label = null): Gt
    {
        $field = new Gt($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a Greater-Than-Or-Equal filter.
     */
    public function gte(string $column, ?string $label = null): Gte
    {
        $field = new Gte($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a Less-Than filter.
     */
    public function lt(string $column, ?string $label = null): Lt
    {
        $field = new Lt($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a Less-Than-Or-Equal filter.
     */
    public function lte(string $column, ?string $label = null): Lte
    {
        $field = new Lte($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a Between range filter.
     */
    public function between(string $column, ?string $label = null): Between
    {
        $field = new Between($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add an In filter.
     */
    public function in(string $column, ?string $label = null): In
    {
        $field = new In($column, $label);
        $this->addField($field);

        return $field;
    }

    /**
     * Add a custom Where closure filter.
     *
     * @param  Closure(Builder<EloquentModel>, mixed): void  $callback
     */
    public function where(Closure $callback, ?string $label = null, ?string $column = null): Where
    {
        $field = new Where($callback, $label, $column);
        $this->addField($field);

        return $field;
    }

    /**
     * Register a filter field.
     */
    public function addField(Field $field): static
    {
        $field->setFilter($this);
        $this->fields[] = $field;

        return $this;
    }

    /**
     * Get all registered filter fields.
     *
     * @return array<int, Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get a specific filter field by column or name.
     */
    public function getField(string $column): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->getColumn() === $column || $field->getName() === $column) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Remove a filter field by column or name.
     */
    public function removeField(string $column): static
    {
        $this->fields = array_values(array_filter(
            $this->fields,
            fn (Field $field): bool => $field->getColumn() !== $column && $field->getName() !== $column,
        ));

        return $this;
    }

    /**
     * Execute filter conditions against the query builder.
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function execute(Builder $query): Builder
    {
        foreach ($this->fields as $field) {
            $field->apply($query);
        }

        return $query;
    }

    /**
     * Apply filter conditions against the query builder (alias).
     *
     * @param  Builder<EloquentModel>  $query
     * @return Builder<EloquentModel>
     */
    public function apply(Builder $query): Builder
    {
        return $this->execute($query);
    }

    /**
     * Check if any filter has active input in current request.
     */
    public function hasActiveFilters(): bool
    {
        foreach ($this->fields as $field) {
            if ($field->hasValue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand filter panel.
     */
    public function expand(bool $expand = true): static
    {
        $this->expanded = $expand;

        return $this;
    }

    /**
     * Collapse filter panel.
     */
    public function collapse(): static
    {
        $this->expanded = false;

        return $this;
    }

    /**
     * Determine if the filter panel is expanded.
     */
    public function isExpanded(): bool
    {
        if ($this->expanded !== null) {
            return $this->expanded;
        }

        return $this->hasActiveFilters();
    }

    /**
     * Get or set the form action URL.
     */
    public function action(?string $url = null): static|string
    {
        if ($url === null) {
            return $this->action ?? request()->url();
        }

        $this->action = $url;

        return $this;
    }

    /**
     * Get unique form element ID.
     */
    public function getId(): string
    {
        if ($this->id === null) {
            $this->id = 'grid_filter_'.spl_object_id($this);
        }

        return $this->id;
    }

    /**
     * Build URL to reset filters while preserving sorting.
     */
    public function resetUrl(): string
    {
        $request = request();
        $url = is_string($this->action) ? $this->action : $request->url();
        /** @var array<string, mixed> $query */
        $query = $request->query();

        $keysToRemove = ['page'];
        foreach ($this->fields as $field) {
            $keysToRemove[] = $field->getName();
            $keysToRemove[] = $field->getColumn();
            $keysToRemove[] = $field->getName().'_start';
            $keysToRemove[] = $field->getName().'_end';
        }

        foreach ($keysToRemove as $key) {
            unset($query[$key]);
        }

        foreach (array_keys($query) as $k) {
            foreach ($this->fields as $field) {
                if ($k === $field->getName() || $k === $field->getColumn()) {
                    unset($query[$k]);
                }
            }
        }

        if (empty($query)) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
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
     * Render the collapsible filter card HTML.
     */
    public function render(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        if (function_exists('view') && view()->exists($this->view)) {
            $filterProxy = new class($this)
            {
                public function __construct(protected Filter $filter) {}

                /**
                 * @param  array<int, mixed>  $args
                 */
                public function __call(string $method, array $args): mixed
                {
                    return $this->filter->{$method}(...$args);
                }

                public function __get(string $name): mixed
                {
                    return $this->filter->{$name};
                }
            };

            return view($this->view, [
                'filter' => $filterProxy,
                'fields' => $this->fields,
                'action' => $this->action(),
                'resetUrl' => $this->resetUrl(),
                'id' => $this->getId(),
                'isExpanded' => $this->isExpanded(),
            ])->render();
        }

        $id = htmlspecialchars($this->getId(), ENT_QUOTES, 'UTF-8');
        $action = htmlspecialchars((string) $this->action(), ENT_QUOTES, 'UTF-8');
        $resetUrl = htmlspecialchars($this->resetUrl(), ENT_QUOTES, 'UTF-8');
        $isExpanded = $this->isExpanded() ? 'true' : 'false';
        $displayStyle = $this->isExpanded() ? '' : 'display: none;';

        $hiddenInputs = '';
        $sortParam = request()->query('_sort');

        if (is_array($sortParam)) {
            foreach ($sortParam as $k => $v) {
                if (is_scalar($v)) {
                    $hiddenInputs .= '<input type="hidden" name="_sort['.htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8').']" value="'.htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8').'">'."\n";
                }
            }
        } elseif (is_string($sortParam) && $sortParam !== '') {
            $hiddenInputs .= '<input type="hidden" name="_sort" value="'.htmlspecialchars($sortParam, ENT_QUOTES, 'UTF-8').'">'."\n";
        }

        $renderedFields = '';
        foreach ($this->fields as $field) {
            $renderedFields .= $field->render()."\n";
        }

        return <<<HTML
<div
    x-data="{ expanded: {$isExpanded} }"
    @toggle-grid-filter.window="expanded = !expanded"
    @toggle-filter.window="expanded = !expanded"
    x-show="expanded"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    class="mb-4 rounded-xl border border-gray-200 bg-white text-gray-950 shadow-xs dark:border-gray-800 dark:bg-gray-900 dark:text-gray-50"
    style="{$displayStyle}"
>
    <form id="{$id}" method="GET" action="{$action}">
        {$hiddenInputs}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4">
            {$renderedFields}
        </div>
        <div class="flex items-center justify-end gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 rounded-b-xl">
            <a href="{$resetUrl}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span>Reset</span>
            </a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3.5 py-1.5 text-xs font-medium text-white hover:bg-blue-700 shadow-xs transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <span>Search</span>
            </button>
        </div>
    </form>
</div>
HTML;
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
