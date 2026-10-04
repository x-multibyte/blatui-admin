<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Tools;

use BlatUI\Admin\Grid\BatchAction;
use Illuminate\Support\Js;
use Throwable;

class BatchDelete extends BatchAction
{
    /**
     * Confirmation prompt text.
     */
    protected string $confirmText = 'Are you sure you want to delete the selected records?';

    /**
     * Toast notification success message.
     */
    protected string $successMessage = 'Selected records deleted successfully';

    /**
     * Warning message when no rows are selected.
     */
    protected string $noSelectedText = 'Please select at least one record';

    /**
     * Create a new BatchDelete action instance.
     */
    public function __construct(?string $title = null)
    {
        parent::__construct($title ?? 'Delete');
    }

    /**
     * Get or set confirmation prompt text.
     */
    public function confirmText(?string $text = null): static|string
    {
        if ($text === null) {
            return $this->confirmText;
        }

        $this->confirmText = $text;

        return $this;
    }

    /**
     * Get or set success toast message.
     */
    public function successMessage(?string $message = null): static|string
    {
        if ($message === null) {
            return $this->successMessage;
        }

        $this->successMessage = $message;

        return $this;
    }

    /**
     * Get or set no-selection warning message.
     */
    public function noSelectedText(?string $message = null): static|string
    {
        if ($message === null) {
            return $this->noSelectedText;
        }

        $this->noSelectedText = $message;

        return $this;
    }

    /**
     * Get batch delete execution URL.
     */
    public function getUrl(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        if ($this->resource !== null) {
            return rtrim($this->resource, '/').'/batch-delete';
        }

        return request()->url().'/batch-delete';
    }

    /**
     * Safely resolve CSRF token.
     */
    protected function getCsrfToken(): string
    {
        try {
            return function_exists('csrf_token') ? (string) csrf_token() : '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Render the batch delete button with confirmation and Fetch API execution.
     */
    public function render(): string
    {
        // Every value below lands inside a JavaScript string literal in the
        // Alpine expression, so each is encoded with Js::from() — which JSON-
        // encodes and then wraps in quotes. The previous implementation used
        // htmlspecialchars(), whose &#039; entities are inert text once the
        // browser has parsed them as JavaScript: an apostrophe reached the user
        // visibly mangled, and a quote could terminate the literal early.
        return trim((string) view('blatui-admin::grid.tools.batch-delete', [
            'url' => Js::from($this->getUrl()),
            'title' => $this->getTitle(),
            'confirmText' => Js::from($this->confirmText),
            'successMessage' => Js::from($this->successMessage),
            'noSelectedText' => Js::from($this->noSelectedText),
            'token' => Js::from($this->getCsrfToken()),
        ]));
    }
}
