<div class="flex flex-col items-center justify-center min-h-[50vh] text-center px-4 py-12">
    <div class="rounded-full bg-red-100 p-4 dark:bg-red-950/50 text-red-600 dark:text-red-400 mb-6">
        <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
    </div>

    <div class="text-sm font-semibold uppercase tracking-wider text-red-600 dark:text-red-400 mb-2">
        {{ $statusCode }} Error
    </div>

    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 sm:text-3xl mb-3">
        {{ $title }}
    </h1>

    <p class="max-w-md text-sm text-gray-500 dark:text-gray-400 mb-8">
        {{ $message }}
    </p>

    <div class="flex items-center gap-4">
        <a
            href="{{ \BlatUI\Admin\Admin::url('/') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-600 dark:hover:bg-blue-700 cursor-pointer"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            <span>Back to Dashboard</span>
        </a>
    </div>
</div>
