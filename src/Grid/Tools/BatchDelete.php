<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Tools;

use BlatUI\Admin\Grid\BatchAction;
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
        $url = htmlspecialchars($this->getUrl(), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($this->getTitle(), ENT_QUOTES, 'UTF-8');
        $confirmText = htmlspecialchars($this->confirmText, ENT_QUOTES, 'UTF-8');
        $successMessage = htmlspecialchars($this->successMessage, ENT_QUOTES, 'UTF-8');
        $noSelectedText = htmlspecialchars($this->noSelectedText, ENT_QUOTES, 'UTF-8');
        $token = $this->getCsrfToken();

        $icon = '<svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';

        return <<<HTML
<button
    type="button"
    @click="
        if (!selectedRows || selectedRows.length === 0) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{$noSelectedText}', type: 'warning' } }));
            return;
        }
        if (confirm('{$confirmText}')) {
            const token = '{$token}' || document.querySelector('meta[name=&quot;csrf-token&quot;]')?.getAttribute('content') || '';
            fetch('{$url}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                    'X-HTTP-Method-Override': 'DELETE'
                },
                body: JSON.stringify({
                    _method: 'DELETE',
                    ids: selectedRows
                })
            }).then(res => {
                if (res.ok) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: '{$successMessage}', type: 'success' } }));
                    setTimeout(() => window.location.reload(), 300);
                } else {
                    res.json().then(data => {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Failed to delete selected records', type: 'error' } }));
                    }).catch(() => {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Failed to delete selected records', type: 'error' } }));
                    });
                }
            }).catch(() => {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Network error while deleting', type: 'error' } }));
            });
        }
    "
    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/50 rounded-sm cursor-pointer transition-colors"
>
    {$icon}
    <span>{$title}</span>
</button>
HTML;
    }
}
