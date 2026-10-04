<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use BlatUI\Admin\Grid\Tools\BatchDelete;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Stringable;

class Tools implements Htmlable, Renderable, Stringable
{
    /**
     * Parent grid instance.
     */
    protected mixed $grid = null;

    /**
     * Resource URI prefix.
     */
    protected ?string $resource = null;

    /**
     * Whether Create button is enabled.
     */
    protected bool $createButton = true;

    /**
     * Custom create URL.
     */
    protected ?string $createUrl = null;

    /**
     * Create button text label.
     */
    protected string $createButtonText = 'Create';

    /**
     * Whether Reload / Refresh button is enabled.
     */
    protected bool $refreshButton = true;

    /**
     * Whether Filter toggle button is enabled.
     */
    protected bool $filterButton = true;

    /**
     * Whether Batch actions are enabled.
     */
    protected bool $batchActions = true;

    /**
     * Registered batch action instances.
     *
     * @var array<string, BatchAction>
     */
    protected array $batchActionItems = [];

    /**
     * Custom tools prepended to the toolbar.
     *
     * @var array<int, mixed>
     */
    protected array $prependedTools = [];

    /**
     * Custom tools appended to the toolbar.
     *
     * @var array<int, mixed>
     */
    protected array $appendedTools = [];

    /**
     * Create a new Tools instance.
     */
    public function __construct(mixed $grid = null)
    {
        $this->grid = $grid;
        $this->addBatchAction(new BatchDelete);
    }

    /**
     * Get parent grid instance.
     */
    public function getGrid(): mixed
    {
        return $this->grid;
    }

    /**
     * Set parent grid instance.
     */
    public function setGrid(mixed $grid): static
    {
        $this->grid = $grid;

        return $this;
    }

    /**
     * Get or set resource URI prefix.
     */
    public function resource(?string $resource = null): static|string|null
    {
        if ($resource === null) {
            return $this->resource;
        }

        $this->resource = $resource;

        return $this;
    }

    /**
     * Get resource URI prefix.
     */
    public function getResource(): ?string
    {
        $res = $this->resource();

        return is_string($res) ? $res : null;
    }

    /**
     * Enable Create button.
     */
    public function enableCreateButton(bool $enable = true): static
    {
        $this->createButton = $enable;

        return $this;
    }

    /**
     * Disable Create button.
     */
    public function disableCreateButton(bool $disable = true): static
    {
        $this->createButton = ! $disable;

        return $this;
    }

    /**
     * Check if Create button is enabled.
     */
    public function isCreateButtonEnabled(): bool
    {
        return $this->createButton;
    }

    /**
     * Get or set Create button URL.
     */
    public function createUrl(?string $url = null): static|string
    {
        if ($url === null) {
            return $this->getCreateUrl();
        }

        $this->createUrl = $url;

        return $this;
    }

    /**
     * Get Create button URL.
     */
    public function getCreateUrl(): string
    {
        if ($this->createUrl !== null) {
            return $this->createUrl;
        }

        if ($this->resource !== null) {
            return rtrim($this->resource, '/').'/create';
        }

        if (is_object($this->grid) && method_exists($this->grid, 'getResource')) {
            $gridResource = $this->grid->getResource();

            if (is_string($gridResource) && $gridResource !== '') {
                return rtrim($gridResource, '/').'/create';
            }
        }

        return request()->url().'/create';
    }

    /**
     * Get or set Create button text.
     */
    public function createButtonText(?string $text = null): static|string
    {
        if ($text === null) {
            return $this->createButtonText;
        }

        $this->createButtonText = $text;

        return $this;
    }

    /**
     * Enable Reload/Refresh button.
     */
    public function enableRefreshButton(bool $enable = true): static
    {
        $this->refreshButton = $enable;

        return $this;
    }

    /**
     * Disable Reload/Refresh button.
     */
    public function disableRefreshButton(bool $disable = true): static
    {
        $this->refreshButton = ! $disable;

        return $this;
    }

    /**
     * Check if Reload/Refresh button is enabled.
     */
    public function isRefreshButtonEnabled(): bool
    {
        return $this->refreshButton;
    }

    /**
     * Enable Reload button (alias).
     */
    public function enableReloadButton(bool $enable = true): static
    {
        return $this->enableRefreshButton($enable);
    }

    /**
     * Disable Reload button (alias).
     */
    public function disableReloadButton(bool $disable = true): static
    {
        return $this->disableRefreshButton($disable);
    }

    /**
     * Check if Reload button is enabled (alias).
     */
    public function isReloadButtonEnabled(): bool
    {
        return $this->isRefreshButtonEnabled();
    }

    /**
     * Enable Filter toggle button.
     */
    public function enableFilterButton(bool $enable = true): static
    {
        $this->filterButton = $enable;

        return $this;
    }

    /**
     * Disable Filter toggle button.
     */
    public function disableFilterButton(bool $disable = true): static
    {
        $this->filterButton = ! $disable;

        return $this;
    }

    /**
     * Check if Filter toggle button is enabled.
     */
    public function isFilterButtonEnabled(): bool
    {
        return $this->filterButton;
    }

    /**
     * Enable Batch actions.
     */
    public function enableBatchActions(bool $enable = true): static
    {
        $this->batchActions = $enable;

        return $this;
    }

    /**
     * Disable Batch actions.
     */
    public function disableBatchActions(bool $disable = true): static
    {
        $this->batchActions = ! $disable;

        return $this;
    }

    /**
     * Check if Batch actions are enabled.
     */
    public function isBatchActionsEnabled(): bool
    {
        return $this->batchActions;
    }

    /**
     * Add a batch action.
     */
    public function addBatchAction(BatchAction $action): static
    {
        if ($this->resource !== null && $action->getResource() === null) {
            $action->setResource($this->resource);
        }

        $this->batchActionItems[get_class($action)] = $action;

        return $this;
    }

    /**
     * Alias for addBatchAction.
     */
    public function add(BatchAction $action): static
    {
        return $this->addBatchAction($action);
    }

    /**
     * Get all registered batch actions.
     *
     * @return array<string, BatchAction>
     */
    public function getBatchActions(): array
    {
        return $this->batchActionItems;
    }

    /**
     * Configure batch actions via closure.
     *
     * @param  Closure(static): void  $callback
     */
    public function batch(Closure $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Configure batch actions via closure (alias).
     *
     * @param  Closure(static): void  $callback
     */
    public function batchActions(Closure $callback): static
    {
        return $this->batch($callback);
    }

    /**
     * Disable batch delete action.
     */
    public function disableBatchDelete(): static
    {
        unset($this->batchActionItems[BatchDelete::class]);

        return $this;
    }

    /**
     * Alias for disableBatchDelete.
     */
    public function disableDelete(): static
    {
        return $this->disableBatchDelete();
    }

    /**
     * Prepend custom tool markup or element.
     */
    public function prepend(mixed $tool): static
    {
        array_unshift($this->prependedTools, $tool);

        return $this;
    }

    /**
     * Append custom tool markup or element.
     */
    public function append(mixed $tool): static
    {
        $this->appendedTools[] = $tool;

        return $this;
    }

    /**
     * Render Create button.
     */
    public function renderCreateButton(): string
    {
        if (! $this->createButton) {
            return '';
        }

        $url = htmlspecialchars($this->getCreateUrl(), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($this->createButtonText, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<a href="{$url}" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 shadow-xs transition-colors cursor-pointer">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    <span>{$text}</span>
</a>
HTML;
    }

    /**
     * Render Reload / Refresh button.
     */
    public function renderRefreshButton(): string
    {
        if (! $this->refreshButton) {
            return '';
        }

        return trim((string) view('blatui-admin::grid.tools.refresh'));
    }

    /**
     * Render Filter toggle button.
     */
    public function renderFilterButton(): string
    {
        if (! $this->filterButton) {
            return '';
        }

        return trim((string) view('blatui-admin::grid.tools.filter'));
    }

    /**
     * Render Batch actions dropdown.
     */
    public function renderBatchActions(): string
    {
        if (! $this->batchActions || empty($this->batchActionItems)) {
            return '';
        }

        $items = '';
        foreach ($this->batchActionItems as $action) {
            if ($this->resource !== null && $action->getResource() === null) {
                $action->setResource($this->resource);
            }
            $items .= $action->render()."\n";
        }

        return <<<HTML
<div x-data="{ open: false }" @click.outside="open = false" class="relative inline-block text-left">
    <button
        type="button"
        @click="open = !open"
        :class="selectedRows.length > 0 ? 'bg-white border-blue-500 text-blue-600 shadow-xs' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700 dark:text-gray-500'"
        :disabled="selectedRows.length === 0"
        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm font-medium transition-colors cursor-pointer"
    >
        <span>Batch Actions</span>
        <span x-show="selectedRows.length > 0" x-text="'(' + selectedRows.length + ')'" class="font-semibold text-blue-600 dark:text-blue-400"></span>
        <svg class="w-4 h-4 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute left-0 z-50 mt-1.5 w-48 origin-top-left rounded-md bg-white p-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-gray-700"
        style="display: none;"
    >
        <div class="py-1">
            {$items}
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Render the toolbar HTML.
     */
    public function render(): string
    {
        $prepended = '';
        foreach ($this->prependedTools as $tool) {
            $prepended .= is_string($tool) ? $tool : (string) $tool;
        }

        $appended = '';
        foreach ($this->appendedTools as $tool) {
            $appended .= is_string($tool) ? $tool : (string) $tool;
        }

        $batchActions = $this->renderBatchActions();
        $filterBtn = $this->renderFilterButton();
        $refreshBtn = $this->renderRefreshButton();
        $createBtn = $this->renderCreateButton();

        return <<<HTML
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2">
        {$batchActions}
        {$prepended}
    </div>
    <div class="flex items-center gap-2 ml-auto">
        {$filterBtn}
        {$refreshBtn}
        {$createBtn}
        {$appended}
    </div>
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
