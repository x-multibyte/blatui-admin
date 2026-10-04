<?php

declare(strict_types=1);

use BlatUI\Admin\Http\Controllers\Resources\AdministratorsController;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    $viewer = Role::query()->firstOrCreate(['slug' => 'viewer'], ['name' => 'Viewer']);
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);
    $jane->roles()->sync([$editor->id]);

    $response = $this->get("/admin/auth/users/{$jane->id}/edit");
    $response->assertOk()->assertSee('Jane');

    expect($response->getContent())
        ->toMatch('/<input\s+type="checkbox"\s+name="roles\[\]"\s+value="'.$editor->id.'"\s+checked/')
        ->not->toMatch('/name="roles\[\]"\s+value="'.$viewer->id.'"\s+checked/');
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

test('administrators grid eager loads roles preventing N+1 queries', function () {
    $editor = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
    for ($i = 1; $i <= 5; $i++) {
        $user = Administrator::query()->create([
            'username' => "user{$i}", 'name' => "User {$i}", 'password' => Hash::make('secret123'),
        ]);
        $user->roles()->sync([$editor->id]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->get('/admin/auth/users')->assertOk();

    $queries = DB::getQueryLog();
    $roleQueries = array_filter($queries, fn (array $q) => str_contains($q['query'], 'admin_roles'));
    expect(count($roleQueries))->toBeLessThanOrEqual(2);
});

test('administrators grid can be filtered by username and name', function () {
    Administrator::query()->create(['username' => 'alice', 'name' => 'Alice Wonder', 'password' => Hash::make('x')]);
    Administrator::query()->create(['username' => 'bob', 'name' => 'Bob Builder', 'password' => Hash::make('x')]);

    $this->get('/admin/auth/users?username=alice')
        ->assertOk()
        ->assertSee('Alice Wonder')
        ->assertDontSee('Bob Builder');

    $this->get('/admin/auth/users?name=Builder')
        ->assertOk()
        ->assertSee('Bob Builder')
        ->assertDontSee('Alice Wonder');
});

test('controller update and edit work directly without active route parameter', function () {
    $editor = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
    $jane = Administrator::query()->create([
        'username' => 'jane', 'name' => 'Jane', 'password' => Hash::make('secret123'),
    ]);
    $jane->roles()->sync([$editor->id]);

    $controller = app(AdministratorsController::class);

    $editContent = $controller->edit($jane->id);
    expect($editContent->render())
        ->toMatch('/<input\s+type="checkbox"\s+name="roles\[\]"\s+value="'.$editor->id.'"\s+checked/');

    $request = Request::create("/admin/auth/users/{$jane->id}", 'PUT', [
        'username' => 'jane',
        'name' => 'Jane Renamed Directly',
    ]);
    $response = $controller->update($request, $jane->id);
    expect($response->isRedirection())->toBeTrue()
        ->and(session('errors'))->toBeNull()
        ->and($jane->fresh()->name)->toBe('Jane Renamed Directly');
});

test('grid displays name correctly when name is the string zero', function () {
    Administrator::query()->create([
        'username' => 'zero_user', 'name' => '0', 'password' => Hash::make('x'),
    ]);

    $response = $this->get('/admin/auth/users')->assertOk();
    expect($response->getContent())->toContain('<span>0</span>');
});

test('validates required fields and minimum password length on create', function () {
    $this->post('/admin/auth/users', [
        'username' => '',
        'name' => '',
        'password' => '123',
    ])->assertSessionHasErrors(['username', 'name', 'password']);
});
