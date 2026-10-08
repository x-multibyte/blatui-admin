<?php

declare(strict_types=1);

use BlatUI\BlatuiServiceProvider;
use Illuminate\Support\Facades\Blade;
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

test('twMerge helper function is available and resolves conflicting classes', function () {
    expect(function_exists('twMerge'))->toBeTrue();
    expect(twMerge('px-2 py-1 bg-red-500', 'bg-blue-500'))->toBe('px-2 py-1 bg-blue-500');
});

test('blade lucide icons are renderable in blade templates', function () {
    $rendered = (string) Blade::render('<x-lucide-check class="w-4 h-4" />');
    expect($rendered)->toContain('<svg')
        ->and($rendered)->toContain('d="M20 6 9 17l-5-5"');
});
