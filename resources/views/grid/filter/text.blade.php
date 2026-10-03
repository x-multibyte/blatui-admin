@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
])

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <div class="relative">
        <input
            id="{{ $id }}"
            type="text"
            name="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
    </div>
</div>
