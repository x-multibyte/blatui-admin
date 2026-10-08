<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

test('all 8 blatui-admin publish tags are properly registered', function () {
    $expectedTags = [
        'blatui-admin',
        'blatui-admin-config',
        'blatui-admin-migrations',
        'blatui-admin-seeders',
        'blatui-admin-routes',
        'blatui-admin-views',
        'blatui-admin-lang',
        'blatui-admin-assets',
    ];

    $registeredGroups = ServiceProvider::publishableGroups();

    foreach ($expectedTags as $tag) {
        expect($registeredGroups)->toContain($tag);
        expect(ServiceProvider::pathsToPublish(null, $tag))->not->toBeEmpty();
    }

    $assetPaths = ServiceProvider::pathsToPublish(null, 'blatui-admin-assets');
    $expectedSource = realpath(dirname(__DIR__, 2).'/dist');
    expect(realpath(array_keys($assetPaths)[0]))->toBe($expectedSource)
        ->and(array_values($assetPaths)[0])->toBe(public_path('vendor/blatui-admin'));
});
