<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = (string) config('blatui-admin.auth.guard', 'admin');

        if (! Auth::guard($guard)->guest() || $this->shouldPassThrough($request)) {
            return $next($request);
        }

        $prefix = trim((string) config('blatui-admin.route.prefix', 'admin'), '/');
        $loginUrl = $prefix ? '/'.$prefix.'/auth/login' : '/auth/login';

        return redirect()->guest($loginUrl);
    }

    /**
     * Determine if the request has a URI that should pass through verification.
     */
    protected function shouldPassThrough(Request $request): bool
    {
        $prefix = trim((string) config('blatui-admin.route.prefix', 'admin'), '/');

        $excepts = [
            $prefix ? $prefix.'/auth/login' : 'auth/login',
            $prefix ? $prefix.'/auth/logout' : 'auth/logout',
        ];

        foreach ($excepts as $except) {
            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
