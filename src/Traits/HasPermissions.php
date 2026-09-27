<?php

declare(strict_types=1);

namespace BlatUI\Admin\Traits;

use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasPermissions
{
    /**
     * Get roles belonging to the administrator.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('blatui-admin.database.roles_model', Role::class);
        $pivotTable = config('blatui-admin.database.role_users_table', 'admin_role_users');

        return $this->belongsToMany($roleModel, $pivotTable, 'user_id', 'role_id')->withTimestamps();
    }

    /**
     * Get all permissions assigned to the administrator (via roles or directly).
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
     * Check if administrator has super administrator role.
     */
    public function isAdministrator(): bool
    {
        return $this->isRole('administrator');
    }

    /**
     * Check if administrator has a specific role.
     */
    public function isRole(string $role): bool
    {
        return $this->roles->contains('slug', $role);
    }

    /**
     * Check if administrator has any of the given roles.
     *
     * @param  array<int, string>  $roles
     */
    public function inRoles(array $roles): bool
    {
        return $this->roles->pluck('slug')->intersect($roles)->isNotEmpty();
    }

    /**
     * Get all permissions merged from all roles.
     *
     * @return Collection<int, Permission>
     */
    public function allPermissions(): Collection
    {
        /** @var Collection<int, Permission> $permissions */
        $permissions = $this->roles->loadMissing('permissions')->pluck('permissions')->flatten()->unique('id');

        return $permissions;
    }
}
