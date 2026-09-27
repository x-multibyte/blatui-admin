<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
        $userModel = config('blatui-admin.database.users_model', Administrator::class);
        $pivotTable = config('blatui-admin.database.role_users_table', 'admin_role_users');

        return $this->belongsToMany($userModel, $pivotTable, 'role_id', 'user_id')->withTimestamps();
    }

    /**
     * Get permissions assigned to the role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        $permissionModel = config('blatui-admin.database.permissions_model', Permission::class);
        $pivotTable = config('blatui-admin.database.role_permissions_table', 'admin_role_permissions');

        return $this->belongsToMany($permissionModel, $pivotTable, 'role_id', 'permission_id')->withTimestamps();
    }

    /**
     * Get menus assigned to the role.
     *
     * @return BelongsToMany<Menu, $this>
     */
    public function menus(): BelongsToMany
    {
        $menuModel = config('blatui-admin.database.menu_model', Menu::class);
        $pivotTable = config('blatui-admin.database.role_menu_table', 'admin_role_menu');

        return $this->belongsToMany($menuModel, $pivotTable, 'role_id', 'menu_id')->withTimestamps();
    }
}
