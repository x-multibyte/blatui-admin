<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Actions;

use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Grid\RowAction;

class QuickEdit extends RowAction
{
    /**
     * Create a new QuickEdit action instance.
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
        return $this->title ?? 'Quick Edit';
    }

    /**
     * Get the target edit action URL.
     */
    public function getUrl(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        $resource = rtrim((string) $this->getResource(), '/');
        $key = (string) $this->getKey();

        if ($resource !== '') {
            return "{$resource}/{$key}/edit";
        }

        return "/{$key}/edit";
    }

    /**
     * Render the quick-edit action trigger element HTML.
     */
    public function render(?Row $row = null): string
    {
        if ($row !== null) {
            $this->setRow($row);
        }

        $url = htmlspecialchars($this->getUrl(), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($this->getTitle(), ENT_QUOTES, 'UTF-8');
        $key = htmlspecialchars((string) $this->getKey(), ENT_QUOTES, 'UTF-8');

        $icon = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>';

        return sprintf(
            '<button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors cursor-pointer" data-url="%s" data-key="%s" @click="$dispatch(\'quick-edit\', { key: \'%s\', url: \'%s\' })" title="%s">%s<span>%s</span></button>',
            $url,
            $key,
            $key,
            $url,
            $title,
            $icon,
            $title,
        );
    }
}
