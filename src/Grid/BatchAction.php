<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Stringable;

abstract class BatchAction implements Htmlable, Renderable, Stringable
{
    /**
     * Action title or label.
     */
    protected ?string $title = null;

    /**
     * Execution URL endpoint.
     */
    protected ?string $url = null;

    /**
     * Resource URI prefix.
     */
    protected ?string $resource = null;

    /**
     * Custom HTML attributes.
     *
     * @var array<string, mixed>
     */
    protected array $htmlAttributes = [];

    /**
     * Create a new BatchAction instance.
     */
    public function __construct(?string $title = null)
    {
        if ($title !== null) {
            $this->title = $title;
        }
    }

    /**
     * Get or set action title.
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
     * Get the action title.
     */
    public function getTitle(): string
    {
        return $this->title ?? 'Action';
    }

    /**
     * Get or set execution URL.
     */
    public function url(?string $url = null): static|string
    {
        if ($url === null) {
            return $this->getUrl();
        }

        $this->url = $url;

        return $this;
    }

    /**
     * Get execution URL.
     */
    public function getUrl(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        if ($this->resource !== null) {
            return rtrim($this->resource, '/').'/batch-action';
        }

        return request()->url().'/batch-action';
    }

    /**
     * Set resource URI prefix.
     */
    public function setResource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Get resource URI prefix.
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Set resource URI prefix (fluent alias).
     */
    public function resource(string $resource): static
    {
        return $this->setResource($resource);
    }

    /**
     * Set an HTML attribute.
     */
    public function attribute(string $key, mixed $value): static
    {
        $this->htmlAttributes[$key] = $value;

        return $this;
    }

    /**
     * Set multiple HTML attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attributes(array $attributes): static
    {
        $this->htmlAttributes = array_merge($this->htmlAttributes, $attributes);

        return $this;
    }

    /**
     * Get HTML attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->htmlAttributes;
    }

    /**
     * Render the batch action markup.
     */
    abstract public function render(): string;

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
