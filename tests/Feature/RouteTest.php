<?php

declare(strict_types=1);

test('admin routes are registered with configured prefix', function () {
    $loginResponse = $this->get('/admin/auth/login');
    $loginResponse->assertStatus(200);

    $logoutResponse = $this->post('/admin/auth/logout');
    $logoutResponse->assertRedirect('/admin/auth/login');
});

test('admin dashboard redirects guest to login', function () {
    $response = $this->get('/admin');
    $response->assertRedirect('/admin/auth/login');
});
