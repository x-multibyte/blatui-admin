@props([
    'filter',
    'fields' => null,
    'action' => null,
    'resetUrl' => null,
    'id' => null,
    'isExpanded' => null,
])

@php
    $fields = $fields ?? (isset($filter) ? $filter->getFields() : []);
    $action = $action ?? (isset($filter) ? $filter->action() : request()->url());
    $resetUrl = $resetUrl ?? (isset($filter) ? $filter->resetUrl() : request()->url());
    $id = $id ?? (isset($filter) ? $filter->getId() : 'grid_filter');
    $isExpanded = $isExpanded ?? (isset($filter) ? $filter->isExpanded() : false);
    $sortParam = request()->query('_sort');
@endphp

@if (! empty($fields))
    <div
        x-data="{ expanded: {{ $isExpanded ? 'true' : 'false' }} }"
        @toggle-grid-filter.window="expanded = !expanded"
        @toggle-filter.window="expanded = !expanded"
        x-show="expanded"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-4 rounded-xl border border-gray-200 bg-white text-gray-950 shadow-xs dark:border-gray-800 dark:bg-gray-900 dark:text-gray-50"
        @unless ($isExpanded) style="display: none;" @endunless
    >
        <form id="{{ $id }}" method="GET" action="{{ $action }}">
            @if (is_array($sortParam))
                @foreach ($sortParam as $k => $v)
                    @if (is_scalar($v))
                        <input type="hidden" name="_sort[{{ $k }}]" value="{{ $v }}">
                    @endif
                @endforeach
            @elseif (is_string($sortParam) && $sortParam !== '')
                <input type="hidden" name="_sort" value="{{ $sortParam }}">
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4">
                @foreach ($fields as $field)
                    {{ $field }}
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-2 px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 rounded-b-xl">
                <a href="{{ $resetUrl }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span>Reset</span>
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3.5 py-1.5 text-xs font-medium text-white hover:bg-blue-700 shadow-xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <span>Search</span>
                </button>
            </div>
        </form>
    </div>
@endif
