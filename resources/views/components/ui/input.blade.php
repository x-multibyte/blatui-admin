@props([
    'type' => 'text',
    'disabled' => false,
    'error' => null,
])

@php
$baseClasses = 'flex h-9 w-full rounded-md border bg-white px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500';

$stateClasses = $error
    ? 'border-red-500 focus-visible:ring-red-500 dark:border-red-500'
    : 'border-gray-300 focus-visible:ring-blue-600 dark:border-gray-700 dark:focus-visible:ring-blue-500';

$classes = trim("$baseClasses $stateClasses");
@endphp

<input
    type="{{ $type }}"
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge(['class' => $classes]) }}
/>
