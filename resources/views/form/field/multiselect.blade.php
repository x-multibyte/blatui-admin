@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => [],
    'options' => [],
    'help' => null,
    'required' => false,
    'searchable' => true,
])

<div
    class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start"
    x-data="{
        query: '',
        allVisible() {
            return Array.from(this.$root.querySelectorAll('input[type=checkbox]'))
                .filter(el => el.offsetParent !== null);
        },
        toggleAll() {
            const visible = this.allVisible();
            const shouldCheck = visible.some(el => !el.checked);
            visible.forEach(el => { el.checked = shouldCheck; });
        }
    }"
>
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-2">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="sm:col-span-3 space-y-1">
        @if ($searchable)
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    x-model="query"
                    placeholder="Search {{ strtolower($label) }}..."
                    class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                />
                <button
                    type="button"
                    @click="toggleAll"
                    class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 shadow-xs transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Select all
                </button>
            </div>
        @endif
        <div id="{{ $id }}" class="max-h-60 space-y-1 overflow-y-auto rounded-md border border-gray-300 p-2 dark:border-gray-700">
            @foreach ($options as $key => $optionLabel)
                <label
                    @if ($searchable) x-show="!query || $el.textContent.toLowerCase().includes(query.toLowerCase())" @endif
                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <input
                        type="checkbox"
                        name="{{ $name }}[]"
                        value="{{ $key }}"
                        @checked(in_array((string) $key, $value, true))
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900"
                    />
                    <span>{{ $optionLabel }}</span>
                </label>
            @endforeach
        </div>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>