<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

class Copyable extends AbstractDisplayer
{
    /**
     * Render Alpine clipboard copy trigger.
     */
    public function display(): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        $text = htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8');
        $copyValue = htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8');

        $copyIcon = '<svg x-show="!copied" class="w-3.5 h-3.5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>';
        $copiedIcon = '<svg x-show="copied" x-cloak class="w-3.5 h-3.5 inline-block text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';

        return sprintf(
            '<div x-data="{ copied: false }" class="inline-flex items-center gap-1.5 group">'
            .'<span>%s</span>'
            .'<button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors cursor-pointer" title="Copy to clipboard" @click="navigator.clipboard.writeText($el.dataset.copyValue); copied = true; setTimeout(() => copied = false, 2000)" data-copy-value="%s">'
            .'%s%s'
            .'</button>'
            .'</div>',
            $text,
            $copyValue,
            $copyIcon,
            $copiedIcon,
        );
    }
}
