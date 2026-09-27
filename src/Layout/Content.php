<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Traits\Macroable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Content implements Htmlable, Renderable, Responsable
{
    use Macroable;

    /**
     * View to render.
     */
    protected string $view = 'blatui-admin::layouts.app';

    /**
     * Page title.
     */
    protected string $title = '';

    /**
     * Page description.
     */
    protected string $description = '';

    /**
     * Breadcrumb trail.
     *
     * @var array<int, array{text: string, url?: string}>
     */
    protected array $breadcrumb = [];

    /**
     * Layout rows.
     *
     * @var array<int, Row>
     */
    protected array $rows = [];

    /**
     * Extra variables to pass to the view.
     *
     * @var array<string, mixed>
     */
    protected array $variables = [];

    /**
     * Content constructor.
     */
    final public function __construct(?Closure $callback = null)
    {
        if ($callback instanceof Closure) {
            $callback($this);
        }
    }

    /**
     * Factory method to create a new instance.
     */
    public static function make(mixed ...$params): static
    {
        return new static(...$params);
    }

    /**
     * Set the page title.
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set the page description.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the page description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Add breadcrumb items.
     *
     * @param  array{text: string, url?: string}  ...$breadcrumbs
     */
    public function breadcrumb(array ...$breadcrumbs): static
    {
        foreach ($breadcrumbs as $crumb) {
            $this->breadcrumb[] = $crumb;
        }

        return $this;
    }

    /**
     * Get the breadcrumb items.
     *
     * @return array<int, array{text: string, url?: string}>
     */
    public function getBreadcrumb(): array
    {
        return $this->breadcrumb;
    }

    /**
     * Set custom view template.
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Get the view template name.
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Pass extra variables to view.
     *
     * @param  array<string, mixed>|string  $key
     */
    public function with(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->variables = array_merge($this->variables, $key);
        } else {
            $this->variables[$key] = $value;
        }

        return $this;
    }

    /**
     * Add a row to the layout.
     */
    public function row(mixed $content): static
    {
        if ($content instanceof Closure) {
            $row = new Row;
            $content($row);
        } elseif ($content instanceof Row) {
            $row = $content;
        } else {
            $row = new Row($content);
        }

        $this->rows[] = $row;

        return $this;
    }

    /**
     * Append content / row to body.
     */
    public function body(mixed $content): static
    {
        return $this->row($content);
    }

    /**
     * Render and append a Blade view template to the body.
     *
     * @param  array<string, mixed>  $data
     */
    public function bodyView(string $view, array $data = []): static
    {
        if (view()->exists($view)) {
            return $this->body(view($view, $data)->render());
        }

        return $this;
    }

    /**
     * Get layout rows.
     *
     * @return array<int, Row>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Render all layout rows to HTML.
     */
    public function renderRows(): string
    {
        $html = '';
        foreach ($this->rows as $row) {
            $html .= view('blatui-admin::layouts.partials.row', ['row' => $row])->render();
        }

        return $html;
    }

    /**
     * Render the full layout HTML.
     */
    public function render(): string
    {
        if (view()->exists($this->view)) {
            $data = array_merge($this->variables, [
                'content' => new HtmlString($this->renderRows()),
                'title' => $this->title,
                'description' => $this->description,
                'breadcrumb' => $this->breadcrumb,
                'rows' => $this->rows,
            ]);

            return view($this->view, $data)->render();
        }

        return $this->renderFallback();
    }

    /**
     * Fallback HTML rendering when view template is not found.
     */
    protected function renderFallback(): string
    {
        return view('blatui-admin::layouts.fallback', [
            'title' => $this->title,
            'description' => $this->description,
            'rows' => $this->rows,
        ])->render();
    }

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): SymfonyResponse
    {
        return new Response($this->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
