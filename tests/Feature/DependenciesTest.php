<?php

declare(strict_types=1);

use BlatUI\BlatuiServiceProvider;
use MallardDuck\LucideIcons\BladeLucideIconsServiceProvider;
use TailwindMerge\Laravel\TailwindMergeServiceProvider;

test('blatui core packages and peer dependencies are registered', function () {
    expect(class_exists(BlatuiServiceProvider::class))->toBeTrue()
        ->and(class_exists(TailwindMergeServiceProvider::class))->toBeTrue()
        ->and(class_exists(BladeLucideIconsServiceProvider::class))->toBeTrue();

    expect(app()->providerIsLoaded(BlatuiServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(TailwindMergeServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(BladeLucideIconsServiceProvider::class))->toBeTrue();
});
