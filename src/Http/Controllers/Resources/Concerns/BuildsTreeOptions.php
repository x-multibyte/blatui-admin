<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

trait BuildsTreeOptions
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $visited
     * @return array<int, string>
     */
    protected function treeOptions(string $modelClass, string $titleColumn, int $depth = 0, ?int $parentId = 0, array &$visited = []): array
    {
        $options = [];
        $items = $modelClass::where('parent_id', $parentId)
            ->orderBy('order')
            ->orderBy($titleColumn)
            ->get();

        foreach ($items as $item) {
            $itemId = (int) $item->getKey();

            if (in_array($itemId, $visited, true)) {
                continue;
            }
            $visited[] = $itemId;

            $prefix = str_repeat('— ', $depth);
            $options[$itemId] = $prefix.$item->getAttribute($titleColumn);

            $options += $this->treeOptions($modelClass, $titleColumn, $depth + 1, $itemId, $visited);
        }

        return $options;
    }
}
