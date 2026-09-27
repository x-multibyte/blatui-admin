<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Layout\Content;
use Closure;
use Illuminate\Routing\Controller;

abstract class AdminController extends Controller
{
    /**
     * Page title.
     */
    protected string $title = 'Admin';

    /**
     * Page description.
     */
    protected string $description = '';

    /**
     * Get title.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Create Content instance with preset title and description.
     */
    protected function content(?Closure $callback = null): Content
    {
        $content = Admin::content($callback);

        if ($this->title !== '') {
            $content->title($this->title);
        }

        if ($this->description !== '') {
            $content->description($this->description);
        }

        return $content;
    }
}
