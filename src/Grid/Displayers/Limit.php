<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use Illuminate\Support\Str;
use Stringable;

class Limit extends AbstractDisplayer
{
    /**
     * Truncate string with Str::limit.
     */
    public function display(int $limit = 30, string $end = '...'): string
    {
        if ($this->value === null || $this->value === '' || (is_array($this->value) && empty($this->value))) {
            return '';
        }

        if (is_scalar($this->value)) {
            $str = (string) $this->value;
        } elseif ($this->value instanceof Stringable || (is_object($this->value) && method_exists($this->value, '__toString'))) {
            $str = (string) $this->value;
        } else {
            $str = json_encode($this->value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return Str::limit($str, $limit, $end);
    }
}
