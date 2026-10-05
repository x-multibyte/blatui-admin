<?php

declare(strict_types=1);

namespace BlatUI\Admin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \BlatUI\Admin\Models\Administrator|null user()
 * @method static int|null id()
 * @method static string version()
 * @method static string title(?string $title = null)
 * @method static \BlatUI\Admin\Layout\Navbar navbar(?\Closure $callback = null)
 * @method static \BlatUI\Admin\Layout\Content content(?\Closure $callback = null)
 * @method static string url(string $path = '')
 * @method static \BlatUI\Admin\Support\Logger logger()
 * @method static void setLogger(?\BlatUI\Admin\Support\Logger $logger)
 * @method static void log(string $level, string $message, array<string, mixed> $context = [])
 *
 * @see \BlatUI\Admin\Admin
 */
class Admin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BlatUI\Admin\Admin::class;
    }
}
