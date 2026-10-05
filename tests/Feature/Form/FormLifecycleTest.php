<?php

declare(strict_types=1);

use BlatUI\Admin\Form;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Repositories\EloquentRepository;
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

test('Form::resolving registers container hook executed before instance is returned', function () {
    $called = false;
    Form::resolving(function (Form $form) use (&$called) {
        $called = true;
        $form->title('Injected Form Title');
    });

    $form = Form::make(Administrator::class);

    expect($called)->toBeTrue()
        ->and($form->getTitle())->toBe('Injected Form Title');
});

test('Form::resolved registers container hook executed after instance is resolved', function () {
    $called = false;
    Form::resolved(function (Form $form) use (&$called) {
        $called = true;
    });

    $form = Form::make(Administrator::class);

    expect($called)->toBeTrue();
});

test('native container resolving hook also intercepts Form::make', function () {
    $called = false;
    app()->resolving(Form::class, function (Form $form) use (&$called) {
        $called = true;
        $form->title('Container Form Title');
    });

    $form = Form::make(Administrator::class);

    expect($called)->toBeTrue()
        ->and($form->getTitle())->toBe('Container Form Title');
});

test('Form::resolving and resolved pass container as second parameter', function () {
    $resolvingContainer = null;
    $resolvedContainer = null;

    Form::resolving(function (Form $form, $app) use (&$resolvingContainer) {
        $resolvingContainer = $app;
    });

    Form::resolved(function (Form $form, $app) use (&$resolvedContainer) {
        $resolvedContainer = $app;
    });

    Form::make(Administrator::class);

    expect($resolvingContainer)->toBe(app())
        ->and($resolvedContainer)->toBe(app());
});

test('Form allows empty repository and can set repository later or throw informative exception', function () {
    $form = Form::make();

    expect(fn () => $form->repository())
        ->toThrow(RuntimeException::class, 'Form repository is not initialized.')
        ->and(fn () => $form->edit(1))
        ->toThrow(RuntimeException::class, 'Form repository is not initialized.')
        ->and(fn () => $form->store(Request::create('/admin/test', 'POST')))
        ->toThrow(RuntimeException::class, 'Form repository is not initialized.')
        ->and(fn () => $form->update(1, Request::create('/admin/test/1', 'POST')))
        ->toThrow(RuntimeException::class, 'Form repository is not initialized.');

    // 1. Pass Repository instance
    $repo = new EloquentRepository(Administrator::class);
    $form->repository($repo);
    expect($form->repository())->toBe($repo);

    // 2. Pass Model class-string
    $form2 = Form::make();
    $form2->repository(Administrator::class);
    expect($form2->repository())->toBeInstanceOf(EloquentRepository::class);

    // 3. Pass Model instance
    $form3 = Form::make();
    $form3->repository(new Administrator);
    expect($form3->repository())->toBeInstanceOf(EloquentRepository::class);
});
