<?php

declare(strict_types=1);

use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Models\Administrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

test('destroy 404s before authorizeDestroy runs, so existence probing cannot leak authorization state', function () {
    $controller = new class extends ResourceController
    {
        public array $calls = [];

        protected function model(): string
        {
            return Administrator::class;
        }

        protected function authorizeDestroy(mixed $record): ?JsonResponse
        {
            $this->calls[] = 'authorizeDestroy';

            return response()->json(['status' => false, 'message' => 'refused'], 403);
        }
    };

    $this->artisan('migrate')->assertSuccessful();

    $missing = $controller->destroy(999999);

    expect($missing->getStatusCode())->toBe(404)
        ->and($controller->calls)->toBe([]);

    $admin = Administrator::query()->create([
        'username' => 'guarded', 'name' => 'Guarded', 'password' => 'x',
    ]);

    $refused = $controller->destroy($admin->id);

    expect($refused->getStatusCode())->toBe(403)
        ->and($controller->calls)->toBe(['authorizeDestroy'])
        ->and(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});

/**
 * A resource controller that refuses to delete any administrator whose
 * username ends in '-protected', standing in for the guards Tasks 4-7 will add
 * ("you cannot delete your own account", "the built-in administrator role
 * cannot be deleted").
 */
function guardedResourceController(): ResourceController
{
    return new class extends ResourceController
    {
        /**
         * Usernames authorizeDestroy() was consulted about, in call order.
         *
         * @var list<string>
         */
        public array $calls = [];

        protected function model(): string
        {
            return Administrator::class;
        }

        protected function authorizeDestroy(mixed $record): ?JsonResponse
        {
            /** @var Administrator $record */
            $this->calls[] = $record->username;

            if (! str_ends_with($record->username, '-protected')) {
                return null;
            }

            return response()->json(['status' => false, 'message' => 'refused'], 403);
        }
    };
}

/**
 * @param  array<int, int>  $ids
 */
function batchRequest(array $ids): Request
{
    return Request::create('/', 'POST', ['ids' => $ids]);
}

/**
 * Rows survive between tests in this file, and admin_users.username is unique,
 * so every fixture carries a per-test prefix.
 */
function makeAdmin(string $username): Administrator
{
    return Administrator::query()->create([
        'username' => $username,
        'name' => ucfirst($username),
        'password' => 'x',
    ]);
}

test('batch delete refuses a record the guard refuses, and the record survives', function () {
    $controller = guardedResourceController();

    $this->artisan('migrate')->assertSuccessful();

    $admin = makeAdmin('refused-protected');

    $response = $controller->batchDestroy(batchRequest([$admin->id]));

    expect($response->getStatusCode())->toBe(403)
        ->and($controller->calls)->toBe(['refused-protected'])
        ->and(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('batch delete still deletes the records the guard allows', function () {
    $controller = guardedResourceController();

    $this->artisan('migrate')->assertSuccessful();

    $first = makeAdmin('allowed-first');
    $second = makeAdmin('allowed-second');

    $response = $controller->batchDestroy(batchRequest([$first->id, $second->id]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toMatchArray(['status' => true, 'message' => 'Deleted successfully'])
        ->and($controller->calls)->toBe(['allowed-first', 'allowed-second'])
        ->and(Administrator::query()->whereKey([$first->id, $second->id])->exists())->toBeFalse();
});

test('batch delete is all or nothing: one refused record aborts the whole batch', function () {
    $controller = guardedResourceController();

    $this->artisan('migrate')->assertSuccessful();

    $permitted = makeAdmin('mixed-permitted');
    $refused = makeAdmin('mixed-protected');

    $response = $controller->batchDestroy(batchRequest([$permitted->id, $refused->id]));

    expect($response->getStatusCode())->toBe(403)
        // Both records are consulted before anything is deleted, so the admin is
        // told the batch failed rather than discovering a partial delete.
        ->and($controller->calls)->toBe(['mixed-permitted', 'mixed-protected'])
        // The permitted sibling survives: the batch is refused wholesale.
        ->and(Administrator::query()->whereKey($permitted->id)->exists())->toBeTrue()
        ->and(Administrator::query()->whereKey($refused->id)->exists())->toBeTrue();
});

test('batch delete 404s before authorizeDestroy runs, so a missing id cannot leak authorization state', function () {
    $controller = guardedResourceController();

    $this->artisan('migrate')->assertSuccessful();

    $admin = makeAdmin('missing-present');

    $response = $controller->batchDestroy(batchRequest([$admin->id, 999999]));

    expect($response->getStatusCode())->toBe(404)
        // Existence is settled for the whole request before any guard runs, so
        // a missing row reports the same 404 whether or not it was guarded.
        ->and($controller->calls)->toBe([])
        ->and(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('batch delete still rejects a malformed ids payload before touching any record', function () {
    $controller = guardedResourceController();

    $this->artisan('migrate')->assertSuccessful();

    $admin = makeAdmin('malformed-present');

    $response = $controller->batchDestroy(Request::create('/', 'POST', ['ids' => ['not-an-id']]));

    expect($response->getStatusCode())->toBe(422)
        ->and($controller->calls)->toBe([])
        ->and(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});
