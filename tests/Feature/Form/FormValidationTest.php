<?php

declare(strict_types=1);

use BlatUI\Admin\Form;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

test('form validates input according to field rules', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('username')->rules('required|min:4');
        $form->text('name')->required();
    });

    // Failing validation with JSON request
    $requestFail = Request::create('/admin/users', 'POST', [
        'username' => 'abc', // less than 4 chars
        // missing name
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $form->store($requestFail);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(422);

    $responseData = $response->getData(true);
    expect($responseData['status'])->toBeFalse()
        ->and($responseData['errors'])->toHaveKey('username')
        ->and($responseData['errors'])->toHaveKey('name');

    // Failing validation with standard request returns RedirectResponse
    $requestStandard = Request::create('/admin/users', 'POST', [
        'username' => 'abc',
    ]);
    $redirectResp = $form->store($requestStandard);
    expect($redirectResp)->toBeInstanceOf(RedirectResponse::class);
});

test('form store persists valid data via repository', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('username')->rules('required');
        $form->text('name')->rules('required');
        $form->hidden('password')->default(bcrypt('password123'));
    });

    $request = Request::create('/admin/users', 'POST', [
        'username' => 'john_doe',
        'name' => 'John Doe',
        'password' => bcrypt('secret'),
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $form->store($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['status'])->toBeTrue()
        ->and($data['message'])->toBe('Created successfully.');

    $user = Administrator::where('username', 'john_doe')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('John Doe');
});

test('form update updates data via repository', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('name')->rules('required');
    });

    $request = Request::create('/admin/users/1', 'POST', [
        'name' => 'Root Administrator',
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $form->update(1, $request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['status'])->toBeTrue()
        ->and($data['message'])->toBe('Updated successfully.');

    $admin = Administrator::find(1);
    expect($admin?->name)->toBe('Root Administrator');
});

test('form returns json response for ajax request and redirect response for standard request', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('name')->rules('required');
        $form->text('username')->rules('required');
        $form->hidden('password')->default(bcrypt('pwd'));
    });
    $form->action('/admin/users');

    // Ajax request returns JsonResponse
    $ajaxRequest = Request::create('/admin/users', 'POST', [
        'name' => 'Ajax User',
        'username' => 'ajax_user',
    ], server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

    $ajaxResponse = $form->store($ajaxRequest);
    expect($ajaxResponse)->toBeInstanceOf(JsonResponse::class)
        ->and($ajaxResponse->getStatusCode())->toBe(200);

    // Standard request returns RedirectResponse
    $standardRequest = Request::create('/admin/users', 'POST', [
        'name' => 'Standard User',
        'username' => 'standard_user',
    ]);

    $standardResponse = $form->store($standardRequest);
    expect($standardResponse)->toBeInstanceOf(RedirectResponse::class)
        ->and($standardResponse->getTargetUrl())->toContain('/admin/users');
});
