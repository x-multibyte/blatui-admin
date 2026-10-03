<?php

declare(strict_types=1);

use BlatUI\Admin\Form;
use BlatUI\Admin\Form\Field\Text;
use BlatUI\Admin\Models\Administrator;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

test('it renders form container with action and method', function () {
    $form = Form::make(new Administrator);
    $form->action('/admin/users')->method('POST')->title('Create Administrator');
    $html = $form->render();

    expect($html)->toContain('Create Administrator')
        ->and($html)->toContain('action="/admin/users"')
        ->and($html)->toContain('method="POST"')
        ->and($html)->toContain('Submit')
        ->and($html)->toContain('Cancel')
        ->and($form)->toBeInstanceOf(Htmlable::class)
        ->and($form)->toBeInstanceOf(Renderable::class)
        ->and($form)->toBeInstanceOf(Responsable::class)
        ->and($form->toHtml())->toBe($html)
        ->and((string) $form)->toBe($html);
});

test('it renders edit form with PUT method spoofing', function () {
    $form = Form::make(new Administrator);
    $form->action('/admin/users/1')->edit(1);
    $html = $form->render();

    expect($form->isEditing())->toBeTrue()
        ->and($form->isCreating())->toBeFalse()
        ->and($form->getKey())->toBe(1)
        ->and($html)->toContain('<input type="hidden" name="_method" value="PUT">');
});

test('it renders all core field types', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('username', 'User Account')->placeholder('Enter username');
        $form->textarea('bio', 'Biography')->rows(5);
        $form->select('role', 'Select Role')->options(['admin' => 'Admin', 'editor' => 'Editor']);
        $form->switch('is_active', 'Active Status');
        $form->datetime('published_at', 'Publish Time');
        $form->hidden('secret_token')->default('xyz123');
        $form->display('created_at', 'Registration Date')->value('2026-01-01');
    });

    $html = $form->render();

    expect($html)->toContain('User Account')
        ->and($html)->toContain('Enter username')
        ->and($html)->toContain('name="username"')
        ->and($html)->toContain('name="bio"')
        ->and($html)->toContain('rows="5"')
        ->and($html)->toContain('name="role"')
        ->and($html)->toContain('Select Role')
        ->and($html)->toContain('Admin')
        ->and($html)->toContain('Editor')
        ->and($html)->toContain('name="is_active"')
        ->and($html)->toContain('role="switch"')
        ->and($html)->toContain('name="published_at"')
        ->and($html)->toContain('type="datetime-local"')
        ->and($html)->toContain('name="secret_token"')
        ->and($html)->toContain('type="hidden"')
        ->and($html)->toContain('xyz123')
        ->and($html)->toContain('Registration Date')
        ->and($html)->toContain('2026-01-01');
});

test('it escapes field labels, values, and placeholders with Blade escaping', function () {
    $form = Form::make(new Administrator, function (Form $form) {
        $form->text('test_xss', '<script>alert("xss")</script>')
            ->value('<b>bold</b>')
            ->placeholder('" onfocus="alert(1)');
    });

    $html = $form->render();

    expect($html)->not->toContain('<script>alert("xss")</script>')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;')
        ->and($html)->not->toContain('value="<b>bold</b>"')
        ->and($html)->toContain('&lt;b&gt;bold&lt;/b&gt;');
});

test('field proxy inside view prevents recursive evaluation loop', function () {
    $field = new Text('title', 'Article Title');
    $reflector = new ReflectionClass($field);
    $method = $reflector->getMethod('newProxy');
    /** @var object $proxy */
    $proxy = $method->invoke($field);

    expect($proxy)->not->toBeInstanceOf(Htmlable::class)
        ->and($proxy)->not->toBeInstanceOf(Renderable::class)
        ->and($proxy)->not->toBeInstanceOf(Stringable::class)
        ->and($proxy->column)->toBe('title')
        ->and($proxy->label)->toBe('Article Title')
        ->and($proxy->getColumn())->toBe('title');
});

test('form responds to request via toResponse', function () {
    $form = Form::make(new Administrator);
    $form->title('User Form');
    $request = Request::create('/admin/users/create');
    $response = $form->toResponse($request);

    expect($response)->toBeInstanceOf(SymfonyResponse::class)
        ->and((string) $response->getContent())->toContain('User Form');
});
