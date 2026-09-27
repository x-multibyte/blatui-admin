@props([
    'variant' => 'default', // default | secondary | destructive | outline | success
])

@php
$baseClasses = 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2';

$variantClasses = match ($variant) {
    'secondary' => 'border-transparent bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100',
    'destructive' => 'border-transparent bg-red-600 text-white hover:bg-red-700',
    'outline' => 'text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700',
    'success' => 'border-transparent bg-emerald-600 text-white hover:bg-emerald-700',
    default => 'border-transparent bg-blue-600 text-white hover:bg-blue-700',
};

$classes = trim("$baseClasses $variantClasses");
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
