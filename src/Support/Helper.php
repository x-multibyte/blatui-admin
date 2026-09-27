<?php

declare(strict_types=1);

namespace BlatUI\Admin\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;

class Helper
{
    /**
     * Render the given content to an HTML string.
     */
    public static function render(mixed $value): string
    {
        if ($value instanceof Renderable) {
            return (string) $value->render();
        }

        if ($value instanceof Htmlable) {
            return (string) $value->toHtml();
        }

        if ($value instanceof Closure) {
            return static::render($value());
        }

        if (is_array($value)) {
            return implode('', array_map([static::class, 'render'], $value));
        }

        return (string) $value;
    }
}
