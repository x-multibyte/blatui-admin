<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use BlatUI\Admin\Grid\Tools\BatchDelete;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\HtmlString;
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

        // Raw values in; Blade's {{ }} is the only escaping layer, matching how
        // Grid\Filter\Field hands its variables to its view.
        return trim((string) view('blatui-admin::grid.tools.create', [
            'url' => $this->getCreateUrl(),
            'text' => $this->createButtonText,
        ]));
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

        foreach ($this->batchActionItems as $action) {
            if ($this->resource !== null && $action->getResource() === null) {
                $action->setResource($this->resource);
            }
        }

        // The action objects are passed through, not their rendered strings:
        // BatchAction implements Htmlable, so Blade's {{ }} routes each one to
        // toHtml(). That keeps {!! !!} out of the views and avoids double-escaping
        // markup the action already escaped itself.
        return trim((string) view('blatui-admin::grid.tools.batch-actions', [
            'actions' => $this->batchActionItems,
        ]));
    }

    /**
     * Render the toolbar HTML.
     */
    public function render(): string
    {
        // Every slot below is either already-rendered HTML (the child render
        // methods) or developer markup accepted by prepend()/append(). All of
        // it is wrapped in HtmlString — the framework's own "already-escaped"
        // contract — so Blade's {{ }} calls toHtml() and emits it verbatim.
        // That is why this view needs no {!! !!}, which AGENTS.md forbids.
        return trim((string) view('blatui-admin::grid.tools.toolbar', [
            'batchActions' => new HtmlString($this->renderBatchActions()),
            'prepended' => new HtmlString($this->joinTools($this->prependedTools)),
            'filterBtn' => new HtmlString($this->renderFilterButton()),
            'refreshBtn' => new HtmlString($this->renderRefreshButton()),
            'createBtn' => new HtmlString($this->renderCreateButton()),
            'appended' => new HtmlString($this->joinTools($this->appendedTools)),
        ]));
    }

    /**
     * Concatenate custom tool fragments into a single HTML string.
     *
     * @param  array<int, mixed>  $tools
     */
    protected function joinTools(array $tools): string
    {
        $html = '';

        foreach ($tools as $tool) {
            $html .= is_string($tool) ? $tool : (string) $tool;
        }

        return $html;
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
