<?php

declare(strict_types=1);

namespace BlatUI\Admin\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait ModelTree
{
    /**
     * Parent column name.
     */
    protected string $parentColumn = 'parent_id';

    /**
     * Title column name.
     */
    protected string $titleColumn = 'title';

    /**
     * Order column name.
     */
    protected string $orderColumn = 'order';

    /**
     * Get primary key column name.
     */
    public function getPrimaryKeyColumn(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get parent column name.
     */
    public function getParentColumn(): string
    {
        return $this->parentColumn;
    }

    /**
     * Get title column name.
     */
    public function getTitleColumn(): string
    {
        return $this->titleColumn;
    }

    /**
     * Get order column name.
     */
    public function getOrderColumn(): string
    {
        return $this->orderColumn;
    }

    /**
     * Get parent node.
     *
     * @return BelongsTo<static, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, $this->getParentColumn());
    }

    /**
     * Get children nodes.
     *
     * @return HasMany<static, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, $this->getParentColumn())->orderBy($this->getOrderColumn());
    }

    /**
     * Convert model items into a hierarchical tree array.
     *
     * @param  Collection<int, static>|null  $elements
     * @param  int  $parentId
     * @return array<int, array<string, mixed>>
     */
    public function toTree(?Collection $elements = null, int $parentId = 0): array
    {
        $elements ??= static::query()->orderBy($this->getOrderColumn())->get();

        $branch = [];

        foreach ($elements as $element) {
            if ((int) $element->{$this->getParentColumn()} === $parentId) {
                $children = $this->toTree($elements, (int) $element->getKey());

                $node = $element->toArray();
                if (! empty($children)) {
                    $node['children'] = $children;
                }

                $branch[] = $node;
            }
        }

        return $branch;
    }
}
