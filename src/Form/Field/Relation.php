<?php

declare(strict_types=1);

namespace BlatUI\Admin\Form\Field;

use BlatUI\Admin\Form\Field;

abstract class Relation extends Field
{
    /**
     * Name of the Eloquent relationship persisted to the pivot table.
     */
    protected string $relation = '';

    /**
     * Get the Eloquent relationship name.
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Set the Eloquent relationship name.
     */
    public function relation(string $name): static
    {
        $this->relation = $name;

        return $this;
    }
}
