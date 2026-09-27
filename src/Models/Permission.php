<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $http_method
 * @property string|null $http_path
 * @property int $order
 * @property int $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
        $roleModel = config('blatui-admin.database.roles_model', Role::class);
        $pivotTable = config('blatui-admin.database.role_permissions_table', 'admin_role_permissions');

        return $this->belongsToMany($roleModel, $pivotTable, 'permission_id', 'role_id')->withTimestamps();
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

        foreach ($paths as $path) {
            if ($path === '*') {
                return true;
            }

            $path = trim($path, '/');
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
