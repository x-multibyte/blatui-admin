@props([
    'id' => '',
    'label' => '',
    'value' => '',
    'help' => null,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">{{ $label }}</span>
    <div class="sm:col-span-3 space-y-1">
        <div class="flex min-h-9 items-center text-sm text-gray-900 dark:text-gray-100">
            {{ $value }}
        </div>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
