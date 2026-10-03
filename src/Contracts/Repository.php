<?php

declare(strict_types=1);

namespace BlatUI\Admin\Contracts;

interface Repository
{
    /**
     * Get the primary key name for the repository.
     */
    public function getKeyName(): string;

    /**
     * Get the created at column name.
     */
    public function getCreatedAtColumn(): ?string;

    /**
     * Get the updated at column name.
     */
    public function getUpdatedAtColumn(): ?string;

    /**
     * Determine if the model uses soft deletes.
     */
    public function isSoftDeletes(): bool;

    /**
     * Get the underlying model or query source.
     */
    public function model(): mixed;

    /**
     * Find a record by its primary key for editing.
     */
    public function edit(mixed $key): mixed;

    /**
     * Store a new record with given values.
     *
     * @param  array<string, mixed>  $values
     */
    public function store(array $values): mixed;

    /**
     * Update a record with given values.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(mixed $key, array $values): bool;

    /**
     * Delete record(s) by primary key.
     */
    public function destroy(mixed $key): bool;
}
