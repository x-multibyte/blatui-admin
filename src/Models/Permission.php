<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $http_method
 * @property string|null $http_path
 * @property int $order
 * @property int $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'http_method',
        'http_path',
        'order',
        'parent_id',
    ];

    /**
     * Get the current table name.
     */
    public function getTable(): string
    {
        return config('blatui-admin.database.permissions_table', parent::getTable());
    }

    /**
     * Get roles having this permission.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = (string) config('blatui-admin.database.role_permissions_table', 'admin_role_permissions');

        return $this->belongsToMany(Role::class, $pivotTable, 'permission_id', 'role_id')->withTimestamps();
    }

    /**
     * Check if the given HTTP request matches this permission.
     */
    public function shouldPassThrough(Request $request): bool
    {
        if (empty($this->http_method) && empty($this->http_path)) {
            return true;
        }

        $methods = array_filter(array_map('trim', explode(',', strtoupper($this->http_method ?? ''))));

        if (! empty($methods) && ! in_array($request->method(), $methods, true)) {
            return false;
        }

        $paths = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $this->http_path ?? ''))));

        $prefix = trim((string) config('blatui-admin.route.prefix', 'admin'), '/');

        foreach ($paths as $path) {
            if ($path === '*') {
                return true;
            }

            if (str_contains($path, ':')) {
                [$pathMethod, $path] = explode(':', $path, 2);
                $pathMethods = array_filter(array_map('trim', explode(',', strtoupper($pathMethod))));

                if (! empty($pathMethods) && ! in_array($request->method(), $pathMethods, true)) {
                    continue;
                }
            }

            $path = trim($path, '/');

            if ($request->is($path)) {
                return true;
            }

            if ($prefix !== '' && $request->is($prefix.'/'.$path)) {
                return true;
            }
        }

        return false;
    }
}
