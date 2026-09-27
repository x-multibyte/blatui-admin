<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use Illuminate\Support\Str;

class Limit extends AbstractDisplayer
{
    /**
     * Truncate string with Str::limit.
     */
    public function display(int $limit = 30, string $end = '...'): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        return Str::limit((string) $this->value, $limit, $end);
    }
}
