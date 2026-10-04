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

test('form calls saving and creating hooks during store', function () {
    $calls = [];

    $form = Form::make(new Administrator, function (Form $form) use (&$calls) {
        $form->text('username')->rules('required');
        $form->text('name')->rules('required');

        $form->saving(function (Form $form) use (&$calls) {
            $calls[] = 'saving';
            $form->setInput('password', bcrypt('secret123'));
        });

        $form->creating(function (Form $form) use (&$calls) {
            $calls[] = 'creating';
        });

        $form->created(function (Form $form, $record) use (&$calls) {
            $calls[] = 'created';
        });

        $form->saved(function (Form $form, $record) use (&$calls) {
            $calls[] = 'saved';
        });
    });

    $request = Request::create('/admin/users', 'POST', [
        'username' => 'newuser',
        'name' => 'New User',
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $form->store($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($calls)->toBe(['saving', 'creating', 'created', 'saved']);

    $created = Administrator::where('username', 'newuser')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('New User');
});

test('form calls saving and updating hooks during update', function () {
    $calls = [];

    $form = Form::make(new Administrator, function (Form $form) use (&$calls) {
        $form->text('name')->rules('required');

        $form->saving(function (Form $form) use (&$calls) {
            $calls[] = 'saving';
        });

        $form->updating(function (Form $form) use (&$calls) {
            $calls[] = 'updating';
        });

        $form->updated(function (Form $form) use (&$calls) {
            $calls[] = 'updated';
        });

        $form->saved(function (Form $form) use (&$calls) {
            $calls[] = 'saved';
        });
    });

    $request = Request::create('/admin/users/1', 'POST', [
        'name' => 'Admin Updated Name',
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $form->update(1, $request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($calls)->toBe(['saving', 'updating', 'updated', 'saved']);

    $admin = Administrator::find(1);
    expect($admin?->name)->toBe('Admin Updated Name');
});

test('form aborts store or update if a saving hook returns false or a response', function () {
    // 1. Abort store by returning false
    $formStoreFalse = Form::make(new Administrator, function (Form $form) {
        $form->text('username');
        $form->text('name');
        $form->saving(function () {
            return false;
        });
    });

    $requestJson = Request::create('/admin/users', 'POST', [
        'username' => 'blocked_user',
        'name' => 'Blocked',
    ], server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $formStoreFalse->store($requestJson);
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(400);

    expect(Administrator::where('username', 'blocked_user')->exists())->toBeFalse();

    // 2. Abort update by returning custom RedirectResponse
    $formUpdateResponse = Form::make(new Administrator, function (Form $form) {
        $form->text('name');
        $form->saving(function () {
            return redirect('/custom-abort-url');
        });
    });

    $requestStandard = Request::create('/admin/users/1', 'POST', [
        'name' => 'Should Not Change',
    ]);

    $updateResp = $formUpdateResponse->update(1, $requestStandard);
    expect($updateResp)->toBeInstanceOf(RedirectResponse::class)
        ->and($updateResp->getTargetUrl())->toContain('/custom-abort-url');

    $admin = Administrator::find(1);
    expect($admin?->name)->not->toBe('Should Not Change');
});

test('form can forget an input value and exclude it from prepared payload', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('username');
        $form->text('password');
    });

    $form->setInput('password', 'secret123');
    expect($form->input('password'))->toBe('secret123');

    $form->forgetInput('password');
    expect($form->input('password'))->toBeNull();

    $prepared = (new ReflectionMethod($form, 'prepareDataForSave'))->invoke($form);
    expect($prepared)->not->toHaveKey('password');
});

test('form can forget dot-notated input values', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('username');
    });

    $form->setInput('meta.info', 'value');
    expect($form->input('meta.info'))->toBe('value');

    $form->forgetInput('meta.info');
    expect($form->input('meta.info'))->toBeNull();
});
