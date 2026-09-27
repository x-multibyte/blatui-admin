<?php

declare(strict_types=1);

namespace BlatUI\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $path
 * @property string $method
 * @property string $ip
 * @property string|null $input
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OperationLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'path',
        'method',
        'ip',
        'input',
    ];

    /**
     * Get the current table name.
     */
    public function getTable(): string
    {
        return config('blatui-admin.database.operation_log_table', parent::getTable());
    }

    /**
     * Get user who performed the operation.
     *
     * @return BelongsTo<Administrator, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'user_id');
    }
}
