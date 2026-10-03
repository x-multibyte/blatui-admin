<?php

declare(strict_types=1);

use BlatUI\Admin\Form;
use BlatUI\Admin\Form\Field;
use BlatUI\Admin\Models\Permission;
use BlatUI\Admin\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

beforeEach(function () {
    // The roles table has no permission_ids column, so Eloquent would otherwise
    // discard a leaked Relation column and keep this suite green for the wrong
    // reason. Strict mode turns that silent discard into a MassAssignmentException.
    Model::preventSilentlyDiscardingAttributes(! Model::preventsSilentlyDiscardingAttributes());

    $this->artisan('migrate')->assertSuccessful();
});

afterEach(function () {
    Model::preventSilentlyDiscardingAttributes(false);
});

/** Minimal pivot-backed field used to exercise Relation without Multiselect. */
function pivotField(string $column, string $relation): Field\Relation
{
    $field = new class($column, $relation) extends Field\Relation
    {
        public function __construct(string $column, string $relation)
        {
            parent::__construct($column);
            $this->relation($relation);
        }
    };

    return $field;
}

test('relation fields are excluded from the model payload', function () {
    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    $form->fill(['name' => 'Editor', 'permission_ids' => [1, 2]]);

    $request = Request::create('/admin/auth/roles', 'POST', [
        'name' => 'Editor',
        'slug' => 'editor',
        'permission_ids' => [1, 2],
    ]);

    $form->store($request);

    // The roles table has no permission_ids column; a leaked column would have thrown.
    expect(Role::query()->where('name', 'Editor')->exists())->toBeTrue();
});

test('relation fields sync the pivot table on store', function () {
    $adminRole = Role::query()->create(['name' => 'Administrator', 'slug' => 'administrator']);
    $editorRole = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

    $permission = Permission::query()->create([
        'name' => 'Users', 'slug' => 'users', 'parent_id' => 0, 'order' => 1,
    ]);

    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    $form->store(Request::create('/admin/auth/roles', 'POST', [
        'name' => 'Author', 'slug' => 'author', 'permission_ids' => [$permission->id],
    ]));

    $created = Role::query()->where('slug', 'author')->firstOrFail();
    expect($created->permissions->pluck('id')->all())->toBe([$permission->id]);
    expect($adminRole->exists)->toBeTrue()->and($editorRole->exists)->toBeTrue();
});

test('sync adds and removes on update', function () {
    $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
    $keep = Permission::query()->create(['name' => 'A', 'slug' => 'a', 'parent_id' => 0, 'order' => 1]);
    $drop = Permission::query()->create(['name' => 'B', 'slug' => 'b', 'parent_id' => 0, 'order' => 2]);
    $add = Permission::query()->create(['name' => 'C', 'slug' => 'c', 'parent_id' => 0, 'order' => 3]);

    $role->permissions()->attach([$keep->id, $drop->id]);

    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    $form->update($role->id, Request::create('/admin/auth/roles/1', 'PUT', [
        'name' => 'Editor', 'slug' => 'editor', 'permission_ids' => [$keep->id, $add->id],
    ]));

    expect($role->fresh()->permissions->pluck('id')->sort()->values()->all())
        ->toBe(collect([$keep->id, $add->id])->sort()->values()->all());
});

test('empty input clears the pivot', function () {
    $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::query()->create(['name' => 'A', 'slug' => 'a', 'parent_id' => 0, 'order' => 1]);
    $role->permissions()->attach([$permission->id]);

    $form = Form::make(Role::class, function (Form $form) {
        $form->text('name');
        $form->text('slug');
        $form->pushField(pivotField('permission_ids', 'permissions'));
    });

    // No permission_ids key at all — the user unchecked everything.
    $form->update($role->id, Request::create('/admin/auth/roles/1', 'PUT', [
        'name' => 'Editor', 'slug' => 'editor',
    ]));

    expect($role->fresh()->permissions)->toHaveCount(0);
});
