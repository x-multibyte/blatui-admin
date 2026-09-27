@props([
    'paginator',
])

@if ($paginator)
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-xs sm:text-sm text-gray-700 dark:text-gray-300">
            @if ($paginator->total() > 0)
                <span>Showing</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $paginator->firstItem() }}</span>
                <span>to</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $paginator->lastItem() }}</span>
                <span>of</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $paginator->total() }}</span>
                <span>records</span>
            @else
                <span>Total <span class="font-semibold text-gray-900 dark:text-gray-100">0</span> records</span>
            @endif
        </div>

        @if ($paginator->hasPages())
            <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center gap-1">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-400 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-600 cursor-not-allowed">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        <span>Prev</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        <span>Prev</span>
                    </a>
                @endif

                {{-- Page Links --}}
                @php
                    $start = max(1, $paginator->currentPage() - 2);
                    $end = min($paginator->lastPage(), $paginator->currentPage() + 2);
                    if ($start > 1) {
                        $start = max(1, $start - max(0, 2 - ($paginator->lastPage() - $paginator->currentPage())));
                    }
                    if ($end < $paginator->lastPage()) {
                        $end = min($paginator->lastPage(), $end + max(0, 3 - $paginator->currentPage()));
                    }
                @endphp

                @if ($start > 1)
                    <a href="{{ $paginator->url(1) }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">1</a>
                    @if ($start > 2)
                        <span class="px-1 text-xs text-gray-400">...</span>
                    @endif
                @endif

                @for ($page = $start; $page <= $end; $page++)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">{{ $page }}</a>
                    @endif
                @endfor

                @if ($end < $paginator->lastPage())
                    @if ($end < $paginator->lastPage() - 1)
                        <span class="px-1 text-xs text-gray-400">...</span>
                    @endif
                    <a href="{{ $paginator->url($paginator->lastPage()) }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">{{ $paginator->lastPage() }}</a>
                @endif

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 shadow-xs transition-colors cursor-pointer">
                        <span>Next</span>
                        <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                @else
                    <span class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-400 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-600 cursor-not-allowed">
                        <span>Next</span>
                        <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif
