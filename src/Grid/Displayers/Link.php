<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use Closure;
use Stringable;

class Link extends AbstractDisplayer
{
    /**
     * Render anchor tag.
     */
    public function display(string|Closure|null $href = null, string $target = '_self'): string
    {
        if ($this->value === null || $this->value === '' || (is_array($this->value) && empty($this->value))) {
            return '';
        }

        if (is_scalar($this->value)) {
            $strVal = (string) $this->value;
        } elseif ($this->value instanceof Stringable || (is_object($this->value) && method_exists($this->value, '__toString'))) {
            $strVal = (string) $this->value;
        } else {
            $strVal = json_encode($this->value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        if ($href === null) {
            $url = $strVal;
        } elseif ($href instanceof Closure) {
            $url = (string) $href($this->value, $this->row);
        } else {
            $url = str_replace('{value}', $strVal, $href);

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
            htmlspecialchars($strVal, ENT_QUOTES, 'UTF-8'),
        );
    }
}
