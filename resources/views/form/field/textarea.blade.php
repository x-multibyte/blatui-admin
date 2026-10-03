@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'help' => null,
    'required' => false,
    'rows' => 4,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="sm:col-span-3 space-y-1">
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            class="flex min-h-[80px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        >{{ $value }}</textarea>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
