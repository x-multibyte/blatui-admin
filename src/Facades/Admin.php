<?php

declare(strict_types=1);

namespace BlatUI\Admin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BlatUI\Admin\Admin
 */
class Admin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BlatUI\Admin\Admin::class;
    }
}
