@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'startVal' => '',
    'endVal' => '',
])

<div class="flex flex-col gap-1.5">
    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <div class="flex items-center gap-1.5">
        <input
            id="{{ $id }}_start"
            type="text"
            name="{{ $name }}[start]"
            value="{{ $startVal }}"
            placeholder="From"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
        <span class="text-xs text-gray-400 shrink-0">—</span>
        <input
            id="{{ $id }}_end"
            type="text"
            name="{{ $name }}[end]"
            value="{{ $endVal }}"
            placeholder="To"
            class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-2.5 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
        />
    </div>
</div>
