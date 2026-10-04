<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Actions;

use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Grid\RowAction;
use Illuminate\Support\Js;
use Throwable;

class Delete extends RowAction
{
    /**
     * Confirmation prompt text.
     */
    protected string $confirmText = 'Are you sure you want to delete this record?';

    /**
     * Toast notification success message.
     */
    protected string $successMessage = 'Deleted successfully';

    /**
     * Create a new Delete action instance.
     */
    public function __construct(?string $title = null)
    {
        if ($title !== null) {
            $this->title = $title;
        }
    }

    /**
     * Get action title with fallback.
     */
    public function getTitle(): string
    {
        return $this->title ?? 'Delete';
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
     * Render the delete action trigger with confirmation popover.
     */
    public function render(?Row $row = null): string
    {
        if ($row !== null) {
            $this->setRow($row);
        }

        // Values split by destination: the token, url and toast message sit
        // inside JavaScript string literals, so Js::from() encodes them for JS
        // (unlike htmlspecialchars(), whose entities become inert text once the
        // browser parses them as JavaScript). The title and confirmation prompt
        // are plain HTML text, so Blade's {{ }} is their escaping layer.
        return trim((string) view('blatui-admin::grid.actions.delete', [
            'url' => Js::from($this->getUrl()),
            'title' => $this->getTitle(),
            'confirmText' => $this->confirmText,
            'successMessage' => Js::from($this->successMessage),
            'token' => Js::from($this->getCsrfToken()),
        ]));
    }
}
