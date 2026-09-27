<?php

declare(strict_types=1);

use BlatUI\Admin\Contracts\Repository;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Repositories\EloquentRepository;
use Database\Seeders\AdminTablesSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

test('eloquent repository implements repository contract', function () {
    $repository = new EloquentRepository(new Administrator);

    expect($repository)->toBeInstanceOf(Repository::class);
});

test('eloquent repository retrieves model metadata and queries records', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $repository = new EloquentRepository(new Administrator);

    expect($repository->getKeyName())->toBe('id')
        ->and($repository->getCreatedAtColumn())->toBe('created_at')
        ->and($repository->getUpdatedAtColumn())->toBe('updated_at')
        ->and($repository->isSoftDeletes())->toBeFalse();

    $admin = $repository->edit(1);
    expect($admin)->not->toBeNull()
        ->and($admin->username)->toBe('admin');
});

test('eloquent repository accepts model class string and builder in constructor', function () {
    $stringRepo = new EloquentRepository(Administrator::class);
    expect($stringRepo->getKeyName())->toBe('id')
        ->and($stringRepo->model())->toBeInstanceOf(Administrator::class);

    $builderRepo = new EloquentRepository(Administrator::query());
    expect($builderRepo->getKeyName())->toBe('id')
        ->and($builderRepo->model())->toBeInstanceOf(Builder::class);
});

test('eloquent repository detects soft deletes and disabled timestamps', function () {
    $softModel = new class extends Model
    {
        use SoftDeletes;
    };

    $noTimestampsModel = new class extends Model
    {
        public $timestamps = false;
    };

    $softRepo = new EloquentRepository($softModel);
    expect($softRepo->isSoftDeletes())->toBeTrue()
        ->and($softRepo->getCreatedAtColumn())->toBe('created_at')
        ->and($softRepo->getUpdatedAtColumn())->toBe('updated_at');

    $noTimestampsRepo = new EloquentRepository($noTimestampsModel);
    expect($noTimestampsRepo->isSoftDeletes())->toBeFalse()
        ->and($noTimestampsRepo->getCreatedAtColumn())->toBeNull()
        ->and($noTimestampsRepo->getUpdatedAtColumn())->toBeNull();
});

test('eloquent repository updates records', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $repository = new EloquentRepository(new Administrator);

    $updated = $repository->update(1, ['name' => 'Updated Admin']);
    expect($updated)->toBeTrue();

    $admin = Administrator::find(1);
    expect($admin?->name)->toBe('Updated Admin');

    $notFoundUpdated = $repository->update(99999, ['name' => 'Non Existent']);
    expect($notFoundUpdated)->toBeFalse();
});

test('eloquent repository destroys single and batch records', function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);

    $repository = new EloquentRepository(new Administrator);

    $user1 = Administrator::create([
        'username' => 'testuser1',
        'password' => bcrypt('password'),
        'name' => 'Test User 1',
    ]);
    $user2 = Administrator::create([
        'username' => 'testuser2',
        'password' => bcrypt('password'),
        'name' => 'Test User 2',
    ]);

    // Test single delete
    $deleted = $repository->destroy($user1->id);
    expect($deleted)->toBeTrue()
        ->and(Administrator::find($user1->id))->toBeNull();

    // Test non-existent delete
    expect($repository->destroy(99999))->toBeFalse();

    // Test batch delete
    $batchDeleted = $repository->destroy([$user2->id]);
    expect($batchDeleted)->toBeTrue()
        ->and(Administrator::find($user2->id))->toBeNull();

    // Test empty batch delete
    expect($repository->destroy([]))->toBeFalse();
});
