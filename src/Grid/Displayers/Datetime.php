<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

class Datetime extends AbstractDisplayer
{
    /**
     * Format date/time using Carbon or DateTime.
     */
    public function display(string $format = 'Y-m-d H:i:s'): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        if ($this->value instanceof DateTimeInterface) {
            return $this->value->format($format);
        }

        if (is_numeric($this->value)) {
            return Carbon::createFromTimestamp((int) $this->value)->format($format);
        }

        if (is_string($this->value)) {
            try {
                return Carbon::parse($this->value)->format($format);
            } catch (Throwable) {
                return $this->value;
            }
        }

        return (string) $this->value;
    }
}
