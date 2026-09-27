@props([
    'title' => $title ?? '',
    'description' => $description ?? '',
    'breadcrumb' => $breadcrumb ?? [],
    'content' => $content ?? null,
])

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    :class="{ 'dark': darkMode }"
    x-data="{
        sidebarCollapsed: false,
        mobileSidebarOpen: false,
        darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)
    }"
    x-init="$watch('darkMode', val => localStorage.setItem('theme', val ? 'dark' : 'light'))"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ ($title ? $title . ' - ' : '') . \BlatUI\Admin\Admin::title() }}</title>

    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('styles')
</head>
<body class="min-h-screen bg-gray-50 font-sans antialiased text-gray-900 dark:bg-gray-950 dark:text-gray-100 flex">
    <!-- Mobile Sidebar Backdrop -->
    <div
        x-show="mobileSidebarOpen"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-40 bg-black/50 lg:hidden"
        @click="mobileSidebarOpen = false"
        style="display: none;"
    ></div>

    <!-- Mobile Sidebar Drawer -->
    <div
        x-show="mobileSidebarOpen"
        x-transition:enter="transition ease-in-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in-out duration-300 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 w-64 lg:hidden"
        style="display: none;"
    >
        @include('blatui-admin::partials.sidebar')
    </div>

    <!-- Desktop Sidebar -->
    <div
        class="hidden lg:block shrink-0 transition-all duration-300"
        :class="sidebarCollapsed ? 'w-16' : 'w-64'"
    >
        @include('blatui-admin::partials.sidebar')
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
        @include('blatui-admin::partials.header', ['breadcrumb' => $breadcrumb])

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @if ($title)
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{{ $title }}</h1>
                    @if ($description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $description }}</p>
                    @endif
                </div>
            @endif

            @if (isset($content))
                {{ $content }}
            @endif

            {{ $slot ?? '' }}

            @yield('content')
        </main>
    </div>

    <!-- Sonner Flash Toast Notifications -->
    <x-blatui-admin::ui.sonner />

    @stack('scripts')
</body>
</html>
