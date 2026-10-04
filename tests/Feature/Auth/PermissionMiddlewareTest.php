<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Feature\Auth;

use BlatUI\Admin\Http\Middleware\Authenticate;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    // Register test routes with route-level middleware parameters
    Route::middleware(['web', Authenticate::class, 'admin.permission'])->prefix('admin/test-perm')->group(function () {
        Route::get('free', fn () => response('free ok'))->middleware('admin.permission:free');
        Route::get('allow-editor', fn () => response('allow ok'))->middleware('admin.permission:allow,editor');
        Route::get('deny-editor', fn () => response('deny ok'))->middleware('admin.permission:deny,editor');
        Route::get('check-users', fn () => response('check ok'))->middleware('admin.permission:check,users');
    });
});

function createEditorUser(): Administrator
{
    /** @var Role $editorRole */
    $editorRole = Role::query()->create([
        'name' => 'Editor',
        'slug' => 'editor',
    ]);

    $usersPermission = Permission::query()->where('slug', 'users')->firstOrFail();
    $editorRole->permissions()->attach($usersPermission);

    /** @var Administrator $user */
    $user = Administrator::query()->create([
        'username' => 'editor_user',
        'name' => 'Editor User',
        'password' => bcrypt('password'),
    ]);

    $user->roles()->attach($editorRole);

    return $user;
}

test('guest user is not blocked with 403 by permission middleware', function () {
    $response = $this->get('/admin/auth/users');
    // Guest should be redirected by Authenticate middleware, NOT get a 403
    $response->assertRedirect('/admin/auth/login');
});

test('when permission is disabled globally, any authenticated user can access any resource', function () {
    config(['blatui-admin.permission.enable' => false]);

    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    // Editor doesn't have roles permission, but permission is globally disabled
    $this->get('/admin/auth/roles')->assertOk();
});

test('whitelisted routes in permission except allow any authenticated user', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    // Dashboard is whitelisted by default
    $this->get('/admin')->assertOk();
});

test('super administrator can access any admin resource', function () {
    /** @var Administrator $admin */
    $admin = Administrator::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($admin, 'admin');

    $this->get('/admin/auth/users')->assertOk();
    $this->get('/admin/auth/roles')->assertOk();
    $this->get('/admin/auth/permissions')->assertOk();
    $this->get('/admin/auth/menu')->assertOk();
});

test('authorized user with users permission can access users resource', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    $this->get('/admin/auth/users')->assertOk();
});

test('unauthorized user without roles permission is denied with 403 on roles resource', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    $this->get('/admin/auth/roles')->assertForbidden();
});

test('unauthorized ajax request receives 403 json response', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    $response = $this->getJson('/admin/auth/roles');

    $response->assertStatus(403)
        ->assertJson([
            'status' => false,
            'message' => 'Permission denied.',
        ]);
});

test('route middleware parameter free bypasses permission check', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    $this->get('/admin/test-perm/free')->assertOk()->assertSee('free ok');
});

test('route middleware parameter allow permits only specified roles', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    // Editor has 'editor' role, so allow,editor should pass
    $this->get('/admin/test-perm/allow-editor')->assertOk()->assertSee('allow ok');

    /** @var Administrator $otherUser */
    $otherUser = Administrator::query()->create([
        'username' => 'guest_user',
        'name' => 'Guest User',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($otherUser, 'admin');

    $this->get('/admin/test-perm/allow-editor')->assertForbidden();
});

test('route middleware parameter deny forbids specified roles', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    // Editor has 'editor' role, so deny,editor should be forbidden
    $this->get('/admin/test-perm/deny-editor')->assertForbidden();

    /** @var Administrator $otherUser */
    $otherUser = Administrator::query()->create([
        'username' => 'guest_user',
        'name' => 'Guest User',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($otherUser, 'admin');

    // guest_user does not have 'editor' role, so should pass
    $this->get('/admin/test-perm/deny-editor')->assertOk()->assertSee('deny ok');
});

test('route middleware parameter check permits only users with specified permission slug', function () {
    $editor = createEditorUser();
    $this->actingAs($editor, 'admin');

    // Editor has 'users' permission, so check,users should pass
    $this->get('/admin/test-perm/check-users')->assertOk()->assertSee('check ok');

    /** @var Administrator $otherUser */
    $otherUser = Administrator::query()->create([
        'username' => 'guest_user',
        'name' => 'Guest User',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($otherUser, 'admin');

    // guest_user has no permissions, so check,users should be forbidden
    $this->get('/admin/test-perm/check-users')->assertForbidden();
});

test('permission shouldPassThrough correctly matches prefixed request path with relative http_path', function () {
    $permission = new Permission([
        'name' => 'Test',
        'slug' => 'test',
        'http_method' => 'GET,POST',
        'http_path' => "/auth/users*\n/auth/roles*",
    ]);

    $request1 = Request::create('/admin/auth/users', 'GET');
    expect($permission->shouldPassThrough($request1))->toBeTrue();

    $request2 = Request::create('/admin/auth/users/create', 'POST');
    expect($permission->shouldPassThrough($request2))->toBeTrue();

    $request3 = Request::create('/admin/auth/roles/1/edit', 'GET');
    expect($permission->shouldPassThrough($request3))->toBeTrue();

    $request4 = Request::create('/admin/auth/menu', 'GET');
    expect($permission->shouldPassThrough($request4))->toBeFalse();

    $request5 = Request::create('/admin/auth/users', 'DELETE');
    expect($permission->shouldPassThrough($request5))->toBeFalse();
});
