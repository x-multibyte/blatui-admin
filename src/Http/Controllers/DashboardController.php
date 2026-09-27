<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers;

use BlatUI\Admin\Layout\Content;

class DashboardController extends AdminController
{
    /**
     * Page title.
     */
    protected string $title = 'Dashboard';

    /**
     * Page description.
     */
    protected string $description = 'Overview';

    /**
     * Show admin dashboard.
     */
    public function index(): Content
    {
        return $this->content()
            ->breadcrumb(
                ['text' => 'Admin', 'url' => '/admin'],
                ['text' => 'Dashboard'],
            )
            ->bodyView('blatui-admin::dashboard');
    }
}
