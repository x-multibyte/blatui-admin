<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Stringable;

abstract class RowAction implements Htmlable, Renderable, Stringable
{
    /**
     * Associated Row instance.
     */
    protected ?Row $row = null;

    /**
     * Target resource URI prefix.
     */
    protected ?string $resource = null;

    /**
     * Primary key value for the row.
     */
    protected mixed $key = null;

    /**
     * Action title or label.
     */
    protected ?string $title = null;

    /**
     * Explicit URL override.
     */
    protected ?string $url = null;

    /**
     * Custom HTML attributes.
     *
     * @var array<string, mixed>
     */
    protected array $htmlAttributes = [];

    /**
     * Set the associated Row instance.
     */
    public function setRow(Row $row): static
    {
        $this->row = $row;

        if ($this->key === null) {
            $this->key = $row->getKey();
        }

        if ($this->resource === null) {
            $this->resource = $row->getResource();
        }

        return $this;
    }

    /**
     * Get the associated Row instance.
     */
    public function getRow(): ?Row
    {
        return $this->row;
    }

    /**
     * Set the primary key value.
     */
    public function setKey(mixed $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get the primary key value.
     */
    public function getKey(): mixed
    {
        return $this->key ?? $this->row?->getKey();
    }

    /**
     * Set the resource path or URL prefix.
     */
    public function setResource(?string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Get the resource path or URL prefix.
     */
    public function getResource(): ?string
    {
        return $this->resource ?? $this->row?->getResource();
    }

    /**
     * Set or get the action title.
     */
    public function title(?string $title = null): static|string
    {
        if ($title === null) {
            return $this->getTitle();
        }

        $this->title = $title;

        return $this;
    }

    /**
     * Get action title.
     */
    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    /**
     * Set action title.
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set explicit URL.
     */
    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the target action URL.
     */
    public function getUrl(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        $resource = rtrim((string) $this->getResource(), '/');
        $key = (string) $this->getKey();

        if ($resource !== '') {
            return "{$resource}/{$key}";
        }

        return "/{$key}";
    }

    /**
     * Set a custom HTML attribute or array of attributes.
     *
     * @param  string|array<string, mixed>  $key
     */
    public function setHtmlAttribute(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->htmlAttributes = array_merge($this->htmlAttributes, $key);
        } else {
            $this->htmlAttributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Get custom HTML attributes.
     *
     * @return array<string, mixed>
     */
    public function getHtmlAttributes(): array
    {
        return $this->htmlAttributes;
    }

    /**
     * Render the action element HTML.
     */
    abstract public function render(?Row $row = null): string;

    /**
     * Get content as a string of HTML.
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Cast object to string.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
