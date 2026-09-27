<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use Stringable;

class Using extends AbstractDisplayer
{
    /**
     * Map dictionary values.
     *
     * @param  array<mixed, string>  $map
     */
    public function display(array $map = [], string $default = ''): string
    {
        if ($this->value === null || $this->value === '' || (is_array($this->value) && empty($this->value))) {
            if (array_key_exists('', $map)) {
                return (string) $map[''];
            }

            return $default;
        }

        if (is_int($this->value) || is_string($this->value)) {
            if (array_key_exists($this->value, $map)) {
                return (string) $map[$this->value];
            }
        } elseif (is_scalar($this->value)) {
            $key = (string) $this->value;

            if (array_key_exists($key, $map)) {
                return (string) $map[$key];
            }
        }

        if ($default !== '') {
            return $default;
        }

        if (is_scalar($this->value)) {
            return (string) $this->value;
        }

        if ($this->value instanceof Stringable || (is_object($this->value) && method_exists($this->value, '__toString'))) {
            return (string) $this->value;
        }

        return json_encode($this->value, JSON_UNESCAPED_UNICODE) ?: '';
    }
}
