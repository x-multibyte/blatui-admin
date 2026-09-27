<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Actions;

use BlatUI\Admin\Grid\Row;
use BlatUI\Admin\Grid\RowAction;

class Show extends RowAction
{
    /**
     * Create a new Show action instance.
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
        return $this->title ?? 'Show';
    }

    /**
     * Render the show link element HTML.
     */
    public function render(?Row $row = null): string
    {
        if ($row !== null) {
            $this->setRow($row);
        }

        $url = htmlspecialchars($this->getUrl(), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($this->getTitle(), ENT_QUOTES, 'UTF-8');

        $icon = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>';

        return sprintf(
            '<a href="%s" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors" title="%s">%s<span>%s</span></a>',
            $url,
            $title,
            $icon,
            $title,
        );
    }
}
