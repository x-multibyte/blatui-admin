@props([
    'variant' => 'default', // default | secondary | outline | ghost | destructive | success
    'size' => 'default',    // default | sm | lg | icon
    'type' => 'button',
])

@php
$baseClasses = 'inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 cursor-pointer shadow-xs';

$variantClasses = match ($variant) {
    'secondary' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80 bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100',
    'outline' => 'border border-input bg-background hover:bg-accent hover:text-accent-foreground border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800',
    'ghost' => 'hover:bg-accent hover:text-accent-foreground hover:bg-gray-100 dark:hover:bg-gray-800 shadow-none',
    'destructive' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90 bg-red-600 text-white hover:bg-red-700',
    'success' => 'bg-emerald-600 text-white hover:bg-emerald-700',
    default => 'bg-primary text-primary-foreground hover:bg-primary/90 bg-blue-600 text-white hover:bg-blue-700',
};

$sizeClasses = match ($size) {
    'sm' => 'h-8 rounded-md px-3 text-xs',
    'lg' => 'h-10 rounded-md px-8 text-base',
    'icon' => 'h-9 w-9 p-0',
    default => 'h-9 px-4 py-2',
};

$classes = trim("$baseClasses $variantClasses $sizeClasses");
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
