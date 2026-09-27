<?php

declare(strict_types=1);

namespace BlatUI\Admin\Contracts;

interface Tree
{
    /**
     * Get primary key column name.
     */
    public function getPrimaryKeyColumn(): string;

    /**
     * Get parent column name.
     */
    public function getParentColumn(): string;

    /**
     * Get title column name.
     */
    public function getTitleColumn(): string;

    /**
     * Get order column name.
     */
    public function getOrderColumn(): string;

    /**
     * Convert model items into a hierarchical tree array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toTree(): array;
}
