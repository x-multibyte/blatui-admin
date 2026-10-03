@props([
    'field' => null,
    'id' => '',
    'name' => '',
    'label' => '',
    'value' => 0,
    'help' => null,
])

<div class="flex flex-col gap-1.5 sm:grid sm:grid-cols-4 sm:gap-4 sm:items-start" x-data="{ state: {{ (bool) $value ? 'true' : 'false' }} }">
    <label for="{{ $id }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:pt-1">
        {{ $label }}
    </label>
    <div class="sm:col-span-3 space-y-1">
        <input type="hidden" name="{{ $name }}" :value="state ? 1 : 0" />
        <button
            type="button"
            id="{{ $id }}"
            role="switch"
            :aria-checked="state.toString()"
            @click="state = !state"
            :class="state ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-700'"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
        >
            <span
                :class="state ? 'translate-x-5' : 'translate-x-0'"
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
            ></span>
        </button>
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>
