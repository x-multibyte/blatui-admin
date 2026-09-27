<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the current table name.
     */
    public function getTable(): string
    {
        return config('blatui-admin.database.roles_table', parent::getTable());
    }

    /**
     * Get users belonging to the role.
     *
     * @return BelongsToMany<Administrator, $this>
     */
    public function users(): BelongsToMany
    {
        $pivotTable = (string) config('blatui-admin.database.role_users_table', 'admin_role_users');

        return $this->belongsToMany(Administrator::class, $pivotTable, 'role_id', 'user_id')->withTimestamps();
    }

    /**
     * Get permissions assigned to the role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        $pivotTable = (string) config('blatui-admin.database.role_permissions_table', 'admin_role_permissions');

        return $this->belongsToMany(Permission::class, $pivotTable, 'role_id', 'permission_id')->withTimestamps();
    }

    /**
     * Get menus assigned to the role.
     *
     * @return BelongsToMany<Menu, $this>
     */
    public function menus(): BelongsToMany
    {
        $pivotTable = (string) config('blatui-admin.database.role_menu_table', 'admin_role_menu');

        return $this->belongsToMany(Menu::class, $pivotTable, 'role_id', 'menu_id')->withTimestamps();
    }
}
