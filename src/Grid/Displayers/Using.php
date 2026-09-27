<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

class Using extends AbstractDisplayer
{
    /**
     * Map dictionary values.
     *
     * @param  array<mixed, string>  $map
     */
    public function display(array $map = [], string $default = ''): string
    {
        if ($this->value === null || $this->value === '') {
            if (array_key_exists('', $map)) {
                return (string) $map[''];
            }

            return $default;
        }

        $key = is_scalar($this->value) ? (string) $this->value : '';

        if (array_key_exists($this->value, $map)) {
            return (string) $map[$this->value];
        }

        if ($key !== '' && array_key_exists($key, $map)) {
            return (string) $map[$key];
        }

        if ($default !== '') {
            return $default;
        }

        return (string) $this->value;
    }
}
