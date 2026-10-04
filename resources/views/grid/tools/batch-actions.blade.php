<div x-data="{ open: false }" @click.outside="open = false" class="relative inline-block text-left">
    <button
        type="button"
        @click="open = !open"
        :class="selectedRows.length > 0 ? 'bg-white border-blue-500 text-blue-600 shadow-xs' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700 dark:text-gray-500'"
        :disabled="selectedRows.length === 0"
        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm font-medium transition-colors cursor-pointer"
    >
        <span>Batch Actions</span>
        <span x-show="selectedRows.length > 0" x-text="'(' + selectedRows.length + ')'" class="font-semibold text-blue-600 dark:text-blue-400"></span>
        <svg class="w-4 h-4 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute left-0 z-50 mt-1.5 w-48 origin-top-left rounded-md bg-white p-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-gray-700"
        style="display: none;"
    >
        <div class="py-1">
            {{-- Each action is an Htmlable, so Blade's e() routes it through
                 toHtml(). That emits the action's already-escaped markup without
                 raw output, which AGENTS.md forbids. --}}
            @foreach ($actions as $action)
                {{ $action }}
            @endforeach
        </div>
    </div>
</div>