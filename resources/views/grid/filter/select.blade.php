@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'options' => [],
    'selectedValues' => [],
])

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        >
            <option value="">All</option>
            @foreach ($options as $key => $optLabel)
                <option value="{{ $key }}"{{ in_array((string) $key, $selectedValues, true) ? ' selected' : '' }}>{{ $optLabel }}</option>
            @endforeach
        </select>
    </div>
</div>
