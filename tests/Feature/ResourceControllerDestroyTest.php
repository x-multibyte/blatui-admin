<?php

declare(strict_types=1);

use BlatUI\Admin\Http\Controllers\ResourceController;
use BlatUI\Admin\Models\Administrator;
use Illuminate\Http\JsonResponse;

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
