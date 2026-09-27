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
        if (view()->exists('blatui-admin::dashboard')) {
            return response()->view('blatui-admin::dashboard');
        }

        return response('<html><head><title>Dashboard</title></head><body><h1>Welcome to BlatUI Admin</h1></body></html>');
    }
}
