<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use Closure;

class Link extends AbstractDisplayer
{
    /**
     * Render anchor tag.
     */
    public function display(string|Closure|null $href = null, string $target = '_self'): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        if ($href === null) {
            $url = (string) $this->value;
        } elseif ($href instanceof Closure) {
            $url = (string) $href($this->value, $this->row);
        } else {
            $url = str_replace('{value}', (string) $this->value, $href);

            if ($this->row !== null) {
                $url = (string) preg_replace_callback('/\{([a-zA-Z0-9_.-]+)\}/', function (array $matches): string {
                    $key = $matches[1];
                    $val = data_get($this->row, $key, '');

                    return is_scalar($val) ? (string) $val : '';
                }, $url);
            }
        }

        $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
        $classes = 'text-blue-600 hover:text-blue-800 underline dark:text-blue-400 dark:hover:text-blue-300 transition-colors';

        return sprintf(
            '<a href="%s" target="%s"%s class="%s">%s</a>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($target, ENT_QUOTES, 'UTF-8'),
            $rel,
            $classes,
            htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8'),
        );
    }
}
