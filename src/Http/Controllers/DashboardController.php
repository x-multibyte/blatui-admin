<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers;

use Illuminate\Http\Response;

class DashboardController extends AdminController
{
    /**
     * Page title.
     */
    protected string $title = 'Dashboard';

    /**
     * Show admin dashboard.
     */
    public function index(): Response
    {
        return response()->view('blatui-admin::dashboard');
    }
}
