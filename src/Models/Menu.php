<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use BlatUI\Admin\Contracts\Tree;
use BlatUI\Admin\Traits\ModelTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $parent_id
 * @property int $order
 * @property string $title
 * @property string|null $icon
 * @property string|null $uri
 * @property string|null $extension
 * @property int $show
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Menu extends Model implements Tree
{
    use ModelTree;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'order',
        'title',
        'icon',
        'uri',
        'extension',
        'show',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parent_id' => 'integer',
        'order' => 'integer',
        'show' => 'integer',
    ];

    /**
     * Get the current table name.
     */
    public function getTable(): string
    {
        return config('blatui-admin.database.menu_table', parent::getTable());
    }

    /**
     * Get roles authorized to access the menu item.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('blatui-admin.database.roles_model', Role::class);
        $pivotTable = config('blatui-admin.database.role_menu_table', 'admin_role_menu');

        return $this->belongsToMany($roleModel, $pivotTable, 'menu_id', 'role_id')->withTimestamps();
    }
}
