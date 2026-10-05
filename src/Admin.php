<?php

declare(strict_types=1);

namespace BlatUI\Admin;

use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Layout\Navbar;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Support\Logger;
use Closure;
use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Throwable;

class Admin
{
    /**
     * Navbar instance.
     */
    protected static ?Navbar $navbar = null;

    /**
     * Title of the admin panel.
     */
    protected static string $title = 'BlatUI Admin';

    /**
     * Get the currently authenticated admin user.
     */
    public static function user(): ?Administrator
    {
        $guard = (string) config('blatui-admin.auth.guard', 'admin');

        try {
            /** @var mixed $user */
            $user = Auth::guard($guard)->user();

            return $user instanceof Administrator ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the currently authenticated admin user ID.
     */
    public static function id(): ?int
    {
        $guard = (string) config('blatui-admin.auth.guard', 'admin');

        try {
            $id = Auth::guard($guard)->id();

            return is_numeric($id) ? (int) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the installed package version.
     *
     * Reads the exact version from Composer's runtime registry. Returns 'dev'
     * when the package is a path-repository or source install without a
     * tagged release (e.g. during active development before the first tag).
     */
    public static function version(): string
    {
        if (! InstalledVersions::isInstalled('x-multibyte/blatui-admin')) {
            return 'dev';
        }

        return InstalledVersions::getPrettyVersion('x-multibyte/blatui-admin') ?? 'dev';
    }

    /**
     * Get or set the admin panel title.
     */
    public static function title(?string $title = null): string
    {
        if ($title !== null) {
            static::$title = $title;
        }

        return (string) config('blatui-admin.title', static::$title);
    }

    /**
     * Get or configure the navbar instance.
     */
    public static function navbar(?Closure $callback = null): Navbar
    {
        if (static::$navbar === null) {
            static::$navbar = new Navbar;
        }

        if ($callback instanceof Closure) {
            $callback(static::$navbar);
        }

        return static::$navbar;
    }

    /**
     * Create a new Content layout instance.
     */
    public static function content(?Closure $callback = null): Content
    {
        return new Content($callback);
    }

    /**
     * Generate an admin relative or absolute URL.
     */
    public static function url(string $path = ''): string
    {
        $prefix = (string) config('blatui-admin.route.prefix', 'admin');
        $cleanPath = ltrim($path, '/');

        if ($cleanPath === '') {
            return '/'.trim($prefix, '/');
        }

        return '/'.trim($prefix, '/').'/'.$cleanPath;
    }

    /**
     * Logger instance.
     */
    protected static ?Logger $logger = null;

    /**
     * Get the admin logger instance.
     */
    public static function logger(): Logger
    {
        if (static::$logger !== null) {
            return static::$logger;
        }

        if (Container::getInstance()->bound(Logger::class)) {
            return Container::getInstance()->make(Logger::class);
        }

        return new Logger;
    }

    /**
     * Set the admin logger instance.
     */
    public static function setLogger(?Logger $logger): void
    {
        static::$logger = $logger;
    }

    /**
     * Log a message with the admin logger.
     *
     * @param  array<string, mixed>  $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        static::logger()->log($level, $message, $context);
    }
}
