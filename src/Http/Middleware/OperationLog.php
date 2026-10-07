<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Middleware;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Models\OperationLog as OperationLogModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OperationLog
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('blatui-admin.operation_log.enable', true)) {
            return $next($request);
        }

        if (Admin::user() === null) {
            return $next($request);
        }

        $allowedMethods = (array) config('blatui-admin.operation_log.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']);

        if (! in_array($request->method(), $allowedMethods, true)) {
            return $next($request);
        }

        $exceptions = (array) config('blatui-admin.operation_log.except', []);
        $path = $request->path();

        foreach ($exceptions as $except) {
            $exceptStr = (string) $except;

            if (str_ends_with($exceptStr, '*')) {
                $prefix = substr($exceptStr, 0, -1);

                if (str_starts_with($path, $prefix) || str_starts_with($path, 'admin/'.$prefix)) {
                    return $next($request);
                }
            } elseif ($path === $exceptStr || $path === 'admin/'.$exceptStr) {
                return $next($request);
            }
        }

        $input = $request->input();
        $sanitizedInput = $this->sanitizeInput($input);

        try {
            $modelClass = config('blatui-admin.database.operation_log_model', OperationLogModel::class);
            $modelClass::create([
                'user_id' => Admin::user()->id,
                'path' => substr($path, 0, 255),
                'method' => $request->method(),
                'ip' => $request->ip() ?? '127.0.0.1',
                'input' => json_encode($sanitizedInput, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            // Logging failures must not break admin requests
            Admin::logger()->error('Failed to record operation log: '.$e->getMessage());
        }

        return $next($request);
    }

    /**
     * Recursively mask secret fields.
     */
    protected function sanitizeInput(mixed $input): mixed
    {
        if (! is_array($input)) {
            return $input;
        }

        $secretFields = (array) config('blatui-admin.operation_log.secret_fields', ['password', 'password_confirmation', '_token']);
        $result = [];

        foreach ($input as $key => $value) {
            if (in_array(strtolower((string) $key), array_map('strtolower', $secretFields), true)) {
                $result[$key] = '******';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitizeInput($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
