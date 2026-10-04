<?php

declare(strict_types=1);

namespace BlatUI\Admin;

use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Layout\Navbar;
use BlatUI\Admin\Models\Administrator;
use Closure;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Auth;

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
        /** @var Administrator|null $user */
        $user = Auth::guard('admin')->user();

        return $user;
    }

    /**
     * Get the currently authenticated admin user ID.
     */
    public static function id(): ?int
    {
        return static::user()?->id;
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
}
