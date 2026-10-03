<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

use BlatUI\Admin\Form\Field;

class Text extends Field
{
    /**
     * Blade view template path.
     */
    protected string $view = 'blatui-admin::form.field.text';
}
