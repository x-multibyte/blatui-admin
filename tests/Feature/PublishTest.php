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
    ];

    $registeredGroups = ServiceProvider::publishableGroups();

    foreach ($expectedTags as $tag) {
        expect($registeredGroups)->toContain($tag);
        expect(ServiceProvider::pathsToPublish(null, $tag))->not->toBeEmpty();
    }
});
