<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Feature\Exceptions;

use BlatUI\Admin\Exceptions\AdminException;
use BlatUI\Admin\Exceptions\ConfigurationException;
use BlatUI\Admin\Exceptions\FormValidationException;
use BlatUI\Admin\Exceptions\PermissionDeniedException;
use BlatUI\Admin\Exceptions\ResourceNotFoundException;
use BlatUI\Admin\Layout\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

test('AdminException renders standardized JSON for JSON requests', function () {
    $exception = new AdminException('Custom error occurred', 500);
    $request = Request::create('/admin/some-endpoint', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $exception->render($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(500);

    $data = $response->getData(true);
    expect($data)->toBe([
        'status' => false,
        'message' => 'Custom error occurred',
        'code' => 500,
        'errors' => [],
    ]);
});

test('AdminException renders within standard admin shell for web requests', function () {
    $exception = new AdminException('Something went wrong', 500);
    $request = Request::create('/admin/some-endpoint', 'GET');

    $response = $exception->render($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toContain('Something went wrong')
        ->and($response->getContent())->toContain('Error')
        ->and($response->getContent())->toContain('<div class="flex flex-col')
        ->and($response->getContent())->not->toContain('&lt;div class="flex flex-col');
});

test('PermissionDeniedException has 403 status and appropriate title and message', function () {
    $exception = new PermissionDeniedException;

    expect($exception->getStatusCode())->toBe(403)
        ->and($exception->getMessage())->toBe('Permission denied.')
        ->and($exception->getTitle())->toBe('Permission denied.');

    $jsonRequest = Request::create('/admin/test', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    /** @var JsonResponse $response */
    $response = $exception->render($jsonRequest);

    expect($response->getStatusCode())->toBe(403);
    $data = $response->getData(true);
    expect($data['status'])->toBeFalse()
        ->and($data['code'])->toBe(403)
        ->and($data['message'])->toBe('Permission denied.');
});

test('ResourceNotFoundException has 404 status and renders 404 response', function () {
    $exception = new ResourceNotFoundException('User not found.');

    expect($exception->getStatusCode())->toBe(404)
        ->and($exception->getTitle())->toBe('Resource Not Found');

    $jsonRequest = Request::create('/admin/test', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    /** @var JsonResponse $response */
    $response = $exception->render($jsonRequest);

    expect($response->getStatusCode())->toBe(404);
    $data = $response->getData(true);
    expect($data['status'])->toBeFalse()
        ->and($data['code'])->toBe(404)
        ->and($data['message'])->toBe('User not found.');
});

test('FormValidationException handles 422 with validation errors and redirects for web requests', function () {
    $errors = ['username' => ['Username is required.']];
    $exception = new FormValidationException('Validation failed', $errors);

    expect($exception->getStatusCode())->toBe(422)
        ->and($exception->getErrors())->toBe($errors);

    // JSON request
    $jsonRequest = Request::create('/admin/test', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    /** @var JsonResponse $jsonResponse */
    $jsonResponse = $exception->render($jsonRequest);

    expect($jsonResponse->getStatusCode())->toBe(422);
    $data = $jsonResponse->getData(true);
    expect($data['status'])->toBeFalse()
        ->and($data['code'])->toBe(422)
        ->and($data['errors'])->toBe($errors);

    // Web request redirects back with input and errors
    $webRequest = Request::create('/admin/test', 'POST');
    $session = app('session.store');
    $webRequest->setLaravelSession($session);

    /** @var RedirectResponse $webResponse */
    $webResponse = $exception->render($webRequest);

    expect($webResponse)->toBeInstanceOf(RedirectResponse::class)
        ->and($session->get('errors')->getBag('default')->get('username'))->toBe(['Username is required.']);
});

test('ConfigurationException has 500 status and renders configuration error', function () {
    $exception = new ConfigurationException('Database configuration missing.');

    expect($exception->getStatusCode())->toBe(500)
        ->and($exception->getTitle())->toBe('Configuration Error');
});

test('AdminException supports context and title modification', function () {
    $exception = (new AdminException('Error with context'))
        ->withContext(['foo' => 'bar'])
        ->setTitle('Custom Title');

    expect($exception->getContext())->toBe(['foo' => 'bar'])
        ->and($exception->getTitle())->toBe('Custom Title');
});

test('AdminException accepts custom status code in constructor', function () {
    $exception = new AdminException('Bad request', 400);

    expect($exception->getStatusCode())->toBe(400)
        ->and($exception->getCode())->toBe(400);

    $exception->setStatusCode(429);
    expect($exception->getStatusCode())->toBe(429)
        ->and($exception->getCode())->toBe(429);
});

test('AdminException renders standardized JSON for AJAX requests without explicit Accept header', function () {
    $exception = new AdminException('AJAX failure', 403);
    $ajaxRequest = Request::create('/admin/some-endpoint', 'POST', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

    $response = $exception->render($ajaxRequest);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(403);

    $data = $response->getData(true);
    expect($data['status'])->toBeFalse()
        ->and($data['code'])->toBe(403)
        ->and($data['message'])->toBe('AJAX failure');
});

test('FormValidationException renders JSON for AJAX requests without explicit Accept header', function () {
    $exception = new FormValidationException('Validation error', ['email' => ['Invalid email']]);
    $ajaxRequest = Request::create('/admin/some-endpoint', 'POST', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

    $response = $exception->render($ajaxRequest);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(422);

    $data = $response->getData(true);
    expect($data['status'])->toBeFalse()
        ->and($data['code'])->toBe(422)
        ->and($data['errors'])->toBe(['email' => ['Invalid email']]);
});

test('FormValidationException and AdminException accept MessageBag directly', function () {
    $bag = new MessageBag(['title' => ['Title is required']]);
    $exception = new FormValidationException('Validation error', $bag);

    expect($exception->getErrors())->toBe(['title' => ['Title is required']]);

    $exception->withErrors(new MessageBag(['slug' => ['Slug is required']]));
    expect($exception->getErrors())->toBe(['slug' => ['Slug is required']]);
});

test('AdminException handles non-HTTP application error codes safely without crashing Symfony Response', function () {
    $exception = new AdminException('Business logic error', 10001);
    $jsonRequest = Request::create('/admin/endpoint', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $exception->render($jsonRequest);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(500);

    $data = $response->getData(true);
    expect($data)->toBe([
        'status' => false,
        'message' => 'Business logic error',
        'code' => 10001,
        'errors' => [],
    ]);

    $webRequest = Request::create('/admin/endpoint', 'GET');
    $webResponse = $exception->render($webRequest);

    expect($webResponse)->toBeInstanceOf(Response::class)
        ->and($webResponse->getStatusCode())->toBe(500);
});

test('AdminException context method provides logging context for Laravel', function () {
    $exception = (new AdminException('Context test'))->withContext(['order_id' => 42]);

    expect($exception->context())->toBe(['order_id' => 42]);
});

test('AdminException escapes HTML in title and message to prevent XSS', function () {
    $exception = new AdminException('<script>alert("xss")</script>');
    $exception->setTitle('<script>alert("title")</script>');

    $response = $exception->render(Request::create('/admin/endpoint', 'GET'));

    expect($response->getContent())
        ->not->toContain('<script>alert("xss")</script>')
        ->not->toContain('<script>alert("title")</script>')
        ->toContain('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;')
        ->toContain('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;');
});

test('AdminException gracefully falls back to standalone error page when master shell rendering throws', function () {
    $exception = new AdminException('Database connection lost', 500);

    Content::macro('render', function () {
        throw new RuntimeException('Layout crashed due to DB disconnect');
    });

    try {
        $response = $exception->render(Request::create('/admin/endpoint', 'GET'));

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getContent())->toContain('Database connection lost')
            ->and($response->getContent())->toContain('Error');
    } finally {
        Content::flushMacros();
    }
});
