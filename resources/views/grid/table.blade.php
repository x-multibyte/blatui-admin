@props([
    'grid',
    'rows' => collect(),
    'columns' => collect(),
    'tools' => null,
    'filter' => null,
    'paginator' => null,
])

<div
    class="grid-table-container space-y-4"
    x-data="{
        selectedRows: [],
        selectAll: false,
        allSelected: false,
        toggleAll() {
            if (this.selectAll || this.allSelected) {
                this.selectedRows = {{ json_encode($rows->map(fn($row) => (string) ($row->getKey() ?? $row->getNumber()))->values()->all()) }};
            } else {
                this.selectedRows = [];
            }
        }
    }"
    x-init="$watch('selectedRows', val => {
        const isAll = val.length > 0 && val.length === {{ $rows->count() }};
        selectAll = isAll;
        allSelected = isAll;
    })"
>
    {{-- Filter Card --}}
    @if ($filter && ! $grid->isFilterDisabled())
        {{ $filter }}
    @endif

    {{-- Tools Bar --}}
    @if ($tools)
        {{ $tools }}
    @endif

    {{-- Table Card Wrapper --}}
    <div class="rounded-xl border border-gray-200 bg-white text-gray-950 shadow-xs dark:border-gray-800 dark:bg-gray-900 dark:text-gray-50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left">
                <thead class="bg-gray-50/75 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        @if ($tools && $tools->isBatchActionsEnabled())
                            <th scope="col" class="px-4 py-3 text-center whitespace-nowrap w-10">
                                <input
                                    type="checkbox"
                                    x-model="selectAll"
                                    @change="allSelected = selectAll; toggleAll()"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 cursor-pointer"
                                />
                            </th>
                        @endif

                        @foreach ($columns as $column)
                            <th
                                scope="col"
                                class="px-4 py-3 text-{{ $column->getAlign() }} text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                @if ($column->getWidth()) style="width: {{ is_numeric($column->getWidth()) ? $column->getWidth() . 'px' : $column->getWidth() }};" @endif
                            >
                                @if ($column->isSortable())
                                    <a
                                        href="{{ $grid->getSortUrl($column->getName()) }}"
                                        class="inline-flex items-center gap-1.5 hover:text-gray-950 dark:hover:text-white transition-colors group cursor-pointer"
                                    >
                                        <span>{{ $column->getLabel() }}</span>
                                        @php
                                            $sortDir = $grid->getSortDirection($column->getName());
                                        @endphp
                                        <span class="inline-flex items-center">
                                            @if ($sortDir === 'asc')
                                                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                            @elseif ($sortDir === 'desc')
                                                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            @else
                                                <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                                            @endif
                                        </span>
                                    </a>
                                @else
                                    <span>{{ $column->getLabel() }}</span>
                                @endif

                                @if ($column->hasHelp())
                                    <span class="ml-1 text-xs text-gray-400" title="{{ $column->getHelp() }}">
                                        <svg class="inline w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </span>
                                @endif
                            </th>
                        @endforeach

                        @if (! $grid->isActionsDisabled())
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                Action
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80 bg-white dark:bg-gray-900">
                    @forelse ($rows as $row)
                        <tr class="border-b border-gray-100 hover:bg-gray-50/80 dark:border-gray-800/80 dark:hover:bg-gray-800/50 transition-colors">
                            @if ($tools && $tools->isBatchActionsEnabled())
                                <td class="px-4 py-3 text-center whitespace-nowrap w-10">
                                    @include('blatui-admin::grid.partials.checkbox', ['row' => $row, 'name' => '_row_id'])
                                </td>
                            @endif

                            @foreach ($columns as $column)
                                <td class="px-4 py-3 text-{{ $column->getAlign() }} text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ $row->cell($column) }}
                                </td>
                            @endforeach

                            @if (! $grid->isActionsDisabled())
                                <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                    @include('blatui-admin::grid.partials.actions', ['row' => $row])
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ $columns->count() + ($tools && $tools->isBatchActionsEnabled() ? 1 : 0) + ($grid->isActionsDisabled() ? 0 : 1) }}"
                                class="py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                <div class="flex flex-col items-center justify-center space-y-3">
                                    <svg class="w-10 h-10 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    <div class="text-sm font-medium">No records found</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Bottom Pagination Bar --}}
        @if ($paginator)
            <div class="border-t border-gray-100 dark:border-gray-800 px-4 py-3 bg-gray-50/50 dark:bg-gray-800/30">
                @include('blatui-admin::grid.pagination', ['paginator' => $paginator])
            </div>
        @endif
    </div>
</div>
