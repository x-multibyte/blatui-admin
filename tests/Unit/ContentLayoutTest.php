<?php

declare(strict_types=1);

use BlatUI\Admin\Layout\Column;
use BlatUI\Admin\Layout\Content;
use BlatUI\Admin\Layout\Navbar;
use BlatUI\Admin\Layout\Row;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

test('content builder manages title, description, breadcrumbs and rows', function () {
    $content = new Content;
    $content->title('User Management')
        ->description('List of users')
        ->breadcrumb(
            ['text' => 'Admin', 'url' => '/admin'],
            ['text' => 'Users', 'url' => '/admin/users'],
        )
        ->row('<div>Row 1</div>')
        ->row(function (Row $row) {
            $row->column(6, '<div>Col 1</div>');
            $row->column(6, '<div>Col 2</div>');
        });

    expect($content->getTitle())->toBe('User Management')
        ->and($content->getDescription())->toBe('List of users')
        ->and($content->getBreadcrumb())->toHaveCount(2)
        ->and($content->getRows())->toHaveCount(2);
});

test('row and column render responsive tailwind grid html', function () {
    $row = new Row;
    $row->column(6, new HtmlString('<p>Left</p>'));
    $row->column(6, new HtmlString('<p>Right</p>'));

    $html = View::make('blatui-admin::layouts.partials.row', ['row' => $row])->render();

    expect($html)->toContain('grid grid-cols-12')
        ->and($html)->toContain('col-span-12 md:col-span-6')
        ->and($html)->toContain(new HtmlString('<p>Left</p>'))
        ->and($html)->toContain(new HtmlString('<p>Right</p>'));
});

test('column supports nested rows and closures', function () {
    $column = new Column(function (Column $col) {
        $col->append(new HtmlString('<span>Direct</span>'));
        $col->row(function (Row $row) {
            $row->column(12, new HtmlString('<span>Nested</span>'));
        });
    }, 8);

    $html = View::make('blatui-admin::layouts.partials.column', ['column' => $column])->render();

    expect($html)->toContain('col-span-12 md:col-span-8')
        ->and($html)->toContain(new HtmlString('<span>Direct</span>'))
        ->and($html)->toContain(new HtmlString('<span>Nested</span>'));
});

test('navbar manages left and right elements', function () {
    $navbar = new Navbar;
    $navbar->left('<button id="search">Search</button>');
    $navbar->right('<div id="user-menu">Profile</div>');

    expect($navbar->render('left'))->toContain('<button id="search">Search</button>')
        ->and($navbar->render('right'))->toContain('<div id="user-menu">Profile</div>')
        ->and($navbar->render('unknown'))->toBe('');
});

test('content implements Responsable and Htmlable', function () {
    $content = Content::make()
        ->title('Dashboard')
        ->description('Admin overview')
        ->body('<div>Main Body</div>');

    expect($content)->toBeInstanceOf(Responsable::class)
        ->and($content)->toBeInstanceOf(Htmlable::class)
        ->and($content)->toBeInstanceOf(Renderable::class);

    $html = $content->toHtml();
    expect($html)->toContain('Dashboard')
        ->and($html)->toContain('Admin overview')
        ->and($html)->toContain('Main Body');

    $request = Request::create('/admin', 'GET');
    $response = $content->toResponse($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain('Dashboard');
});
