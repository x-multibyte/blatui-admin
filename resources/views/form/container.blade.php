@props([
    'form' => null,
    'action' => '',
    'method' => 'POST',
    'title' => null,
    'fields' => [],
    'isEditing' => false,
])

<div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    @if ($title)
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">{{ $title }}</h3>
        </div>
    @endif

    <form
        action="{{ $action }}"
        method="POST"
        class="p-6 space-y-6"
        x-data="{ submitting: false }"
        @submit="submitting = true"
    >
        @csrf
        @if ($isEditing && strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <div class="space-y-4">
            @foreach ($fields as $field)
                {{ $field }}
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
            <button
                type="button"
                onclick="window.history.back()"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="submit"
                :disabled="submitting"
                class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:opacity-50 cursor-pointer"
            >
                <span x-show="!submitting">Submit</span>
                <span x-show="submitting" style="display: none;">Saving...</span>
            </button>
        </div>
    </form>
</div>
