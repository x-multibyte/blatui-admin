<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers;

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
}
