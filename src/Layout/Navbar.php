<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

use BlatUI\Admin\Support\Helper;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;

class Navbar implements Htmlable, Renderable
{
    /**
     * Navbar elements.
     *
     * @var array{left: array<int, mixed>, right: array<int, mixed>}
     */
    protected array $elements = [
        'left' => [],
        'right' => [],
    ];

    /**
     * Add element to the left side of navbar.
     */
    public function left(mixed $element): static
    {
        $this->elements['left'][] = $element;

        return $this;
    }

    /**
     * Add element to the right side of navbar.
     */
    public function right(mixed $element): static
    {
        $this->elements['right'][] = $element;

        return $this;
    }

    /**
     * Render a section of the navbar.
     */
    public function render(string $part = 'right'): string
    {
        if (! isset($this->elements[$part]) || empty($this->elements[$part])) {
            return '';
        }

        $html = '';
        foreach ($this->elements[$part] as $element) {
            $html .= Helper::render($element);
        }

        return $html;
    }

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render('right');
    }
}
