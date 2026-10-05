<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Middleware;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Exceptions\PermissionDeniedException;
use BlatUI\Admin\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Permission
{
    /**
     * Middleware prefix for explicit route parameters.
     */
    protected string $middlewarePrefix = 'admin.permission:';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $user = Admin::user();

        if (! $user) {
            return $next($request);
        }

        if (! (bool) config('blatui-admin.permission.enable', true)) {
            return $next($request);
        }

        if ($this->shouldPassThrough($request)) {
            return $next($request);
        }

        if ($user->isAdministrator()) {
            return $next($request);
        }

        // If called directly with middleware parameters
        if (! empty($args)) {
            if ($this->checkArgs($user, array_values($args))) {
                return $next($request);
            }

            return $this->deny($request);
        }

        // If the current route has route-level admin.permission:* middleware
        $routeCheck = $this->checkRoutePermission($request, $user);

        if ($routeCheck !== null) {
            if ($routeCheck) {
                return $next($request);
            }

            return $this->deny($request);
        }

        // Default RBAC check
        $hasPermission = $user->allPermissions()->contains(
            fn (\BlatUI\Admin\Models\Permission $permission): bool => $permission->shouldPassThrough($request),
        );

        if (! $hasPermission) {
            return $this->deny($request);
        }

        return $next($request);
    }

    /**
     * Check if the current route has an explicit admin.permission:* middleware.
     *
     * Returns true if allowed, false if denied, or null if no route-level middleware found.
     */
    protected function checkRoutePermission(Request $request, Administrator $user): ?bool
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        /** @var string|null $middleware */
        $middleware = collect($route->middleware())->first(function (string $m): bool {
            return str_starts_with($m, $this->middlewarePrefix);
        });

        if (! $middleware) {
            return null;
        }

        $params = array_values(array_filter(array_map('trim', explode(',', substr($middleware, strlen($this->middlewarePrefix))))));

        return $this->checkArgs($user, $params);
    }

    /**
     * Check route middleware parameter arguments.
     *
     * @param  array<int, string>  $args
     */
    protected function checkArgs(Administrator $user, array $args): bool
    {
        $action = array_shift($args);

        return match ($action) {
            'free' => true,
            'allow' => $user->inRoles($args),
            'deny' => ! $user->inRoles($args),
            'check' => $user->allPermissions()->pluck('slug')->intersect($args)->isNotEmpty(),
            default => false,
        };
    }

    /**
     * Determine if the request has a URI that should pass through verification.
     */
    protected function shouldPassThrough(Request $request): bool
    {
        $prefix = trim((string) config('blatui-admin.route.prefix', 'admin'), '/');

        /** @var array<int, string> $excepts */
        $excepts = (array) config('blatui-admin.permission.except', [
            '/',
            'auth/login',
            'auth/logout',
        ]);

        foreach ($excepts as $except) {
            if (str_contains($except, ':')) {
                [$method, $except] = explode(':', $except, 2);
                $methods = array_filter(array_map('trim', explode(',', strtoupper($method))));

                if (! empty($methods) && ! in_array($request->method(), $methods, true)) {
                    continue;
                }
            }

            $except = trim($except, '/');

            if ($except === '') {
                if ($request->is($prefix !== '' ? $prefix : '/')) {
                    return true;
                }

                continue;
            }

            if ($request->is($except)) {
                return true;
            }

            if ($prefix !== '' && $request->is($prefix.'/'.$except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send a denied response.
     */
    protected function deny(Request $request): Response
    {
        $message = trans('blatui-admin::admin.deny');

        if (! is_string($message) || $message === 'blatui-admin::admin.deny') {
            $message = 'Permission denied.';
        }

        Admin::logger()->warning('Admin access denied', [
            'admin_user_id' => Admin::id(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
        ]);

        throw new PermissionDeniedException($message);
    }
}
