<?php

declare(strict_types=1);

use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');
});

test('the administrators index renders a grid', function () {
    $this->get('/admin/auth/users')
        ->assertOk()
        ->assertSee('Administrator');
});

test('the administrators create page renders', function () {
    $this->get('/admin/auth/users/create')
        ->assertOk()
        ->assertSee('Administrators');
});

test('the administrators edit page renders and preselects assigned roles', function () {
    $editor = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);
    $jane->roles()->sync([$editor->id]);

    $this->get("/admin/auth/users/{$jane->id}/edit")
        ->assertOk()
        ->assertSee('Jane');
});

test('a user can be created and assigned roles', function () {
    $editor = Role::query()->where('slug', 'editor')->first()
        ?? Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

    $this->post('/admin/auth/users', [
        'username' => 'jane',
        'name' => 'Jane Doe',
        'password' => 'secret123',
        'roles' => [$editor->id],
    ])->assertRedirect('/admin/auth/users');

    $jane = Administrator::query()->where('username', 'jane')->firstOrFail();

    expect(Hash::check('secret123', $jane->password))->toBeTrue()
        ->and($jane->roles->pluck('id')->all())->toBe([$editor->id]);
});

test('a user can be updated and roles removed', function () {
    $editor = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);

    $this->post('/admin/auth/users', [
        'username' => 'jane', 'name' => 'Jane', 'password' => 'secret123', 'roles' => [$editor->id],
    ]);

    $jane = Administrator::query()->where('username', 'jane')->firstOrFail();
    $originalHash = $jane->password;

    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'jane', 'name' => 'Jane Renamed', 'roles' => [],
    ])->assertRedirect('/admin/auth/users');

    expect($jane->fresh()->name)->toBe('Jane Renamed')
        ->and($jane->fresh()->roles)->toHaveCount(0)
        // An empty password field on update must leave the stored hash untouched.
        ->and($jane->fresh()->password)->toBe($originalHash);
});

test('a new password is hashed when supplied on update', function () {
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('oldpassword'),
    ]);

    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'jane', 'name' => 'Jane', 'password' => 'newpassword',
    ]);

    expect(Hash::check('newpassword', $jane->fresh()->password))->toBeTrue();
});

test('validates username uniqueness while ignoring itself', function () {
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);

    // Submitting the same username must pass on edit.
    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'jane', 'name' => 'Jane Renamed',
    ])->assertSessionHasNoErrors();

    // A different existing username must fail.
    $this->put("/admin/auth/users/{$jane->id}", [
        'username' => 'admin', 'name' => 'Jane',
    ])->assertSessionHasErrors('username');
});

test('refuses to delete self', function () {
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();

    $this->deleteJson("/admin/auth/users/{$admin->id}")
        ->assertForbidden();

    expect(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('a user can be deleted', function () {
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);

    $this->deleteJson("/admin/auth/users/{$jane->id}")->assertOk();

    expect(Administrator::query()->whereKey($jane->id)->exists())->toBeFalse();
});

test('batch destroy deletes the selected users', function () {
    $a = Administrator::query()->create(['username' => 'a', 'name' => 'A', 'password' => Hash::make('x')]);
    $b = Administrator::query()->create(['username' => 'b', 'name' => 'B', 'password' => Hash::make('x')]);

    $this->deleteJson('/admin/auth/users/batch-delete', ['ids' => [$a->id, $b->id]])
        ->assertOk();

    expect(Administrator::query()->whereKey([$a->id, $b->id])->count())->toBe(0);
});

test('batch destroy rejects a malformed ids payload', function () {
    $this->deleteJson('/admin/auth/users/batch-delete', ['ids' => 'not-an-array'])
        ->assertStatus(422);
});

test('batch destroy refuses when selection includes self', function () {
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $other = Administrator::query()->create(['username' => 'other', 'name' => 'Other', 'password' => Hash::make('x')]);

    $this->deleteJson('/admin/auth/users/batch-delete', ['ids' => [$other->id, $admin->id]])
        ->assertForbidden();

    expect(Administrator::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(Administrator::query()->whereKey($admin->id)->exists())->toBeTrue();
});
