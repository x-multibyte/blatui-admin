<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use BlatUI\Admin\Traits\HasPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $name
 * @property string|null $avatar
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Administrator extends Authenticatable
{
    use HasPermissions;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the current table name.
     */
    public function getTable(): string
    {
        return config('blatui-admin.database.users_table', parent::getTable());
    }

    /**
     * Get avatar URL.
     */
    public function getAvatarUrl(): string
    {
        if ($this->avatar && Storage::disk(config('blatui-admin.upload.disk', 'public'))->exists($this->avatar)) {
            return Storage::disk(config('blatui-admin.upload.disk', 'public'))->url($this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name ?: $this->username).'&color=7F9CF5&background=EBF4FF';
    }
}
