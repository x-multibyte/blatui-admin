@props([
    'orientation' => 'horizontal', // horizontal | vertical
])

@php
$classes = $orientation === 'vertical'
    ? 'shrink-0 bg-gray-200 dark:bg-gray-800 h-full w-[1px]'
    : 'shrink-0 bg-gray-200 dark:bg-gray-800 h-[1px] w-full';
@endphp

<div role="none" {{ $attributes->merge(['class' => $classes]) }}></div>
