<?php

declare(strict_types=1);

namespace BlatUI\Admin\View\Composers;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class LayoutComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        foreach ($data as $key => $value) {
            $view->with($key, $this->sanitize($value));
        }
    }

    /**
     * Sanitize a value before passing to layout views.
     */
    protected function sanitize(mixed $value): mixed
    {
        if ($value instanceof Htmlable) {
            return $value;
        }

        if (is_string($value)) {
            return new HtmlString(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized[$k] = $this->sanitize($v);
            }

            return $sanitized;
        }

        return $value;
    }
}
