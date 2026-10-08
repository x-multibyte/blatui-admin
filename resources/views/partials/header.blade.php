@php
$user = \BlatUI\Admin\Admin::user();
$breadcrumb = $breadcrumb ?? [];
$navbarRight = \BlatUI\Admin\Admin::navbar()->render('right');
$navbarLeft = \BlatUI\Admin\Admin::navbar()->render('left');
@endphp

<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white/80 px-4 backdrop-blur-md sm:px-6 dark:border-gray-800 dark:bg-gray-900/80">
    <div class="flex items-center gap-3">
        <!-- Mobile Sidebar Toggle -->
        <button
            type="button"
            @click="mobileSidebarOpen = true"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 lg:hidden dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
            aria-label="Open sidebar"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" x2="20" y1="12" y2="12" /><line x1="4" x2="20" y1="6" y2="6" /><line x1="4" x2="20" y1="18" y2="18" />
            </svg>
        </button>

        <!-- Desktop Sidebar Toggle -->
        <button
            type="button"
            @click="sidebarCollapsed = !sidebarCollapsed"
            class="hidden h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 lg:flex dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
            aria-label="Toggle sidebar"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="18" x="3" y="3" rx="2" /><path d="M9 3v18" />
            </svg>
        </button>

        <!-- Custom Navbar Left -->
        @if ($navbarLeft)
            <div class="flex items-center gap-2">
                {{ $navbarLeft }}
            </div>
        @endif

        <!-- Breadcrumb -->
        @if (! empty($breadcrumb))
            <nav class="hidden sm:flex items-center space-x-1.5 text-xs text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
                <a href="{{ \BlatUI\Admin\Admin::url('/') }}" class="hover:text-gray-900 dark:hover:text-gray-100">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                </a>
                @foreach ($breadcrumb as $crumb)
                    <svg class="h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                    @if (! empty($crumb['url']) && ! $loop->last)
                        <a href="{{ $crumb['url'] }}" class="hover:text-gray-900 dark:hover:text-gray-100">{{ $crumb['text'] }}</a>
                    @else
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $crumb['text'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif
    </div>

    <!-- Right Header Elements -->
    <div class="flex items-center gap-2 sm:gap-3">
        <!-- Custom Navbar Right -->
        @if ($navbarRight)
            <div class="flex items-center gap-2">
                {{ $navbarRight }}
            </div>
        @endif

        <!-- Dark Mode Toggle Button -->
        <button
            type="button"
            @click="$store.theme.toggle()"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
            aria-label="Toggle dark mode"
        >
            <svg x-show="!$store.theme.isDark" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
            </svg>
            <svg x-show="$store.theme.isDark" class="h-4 w-4" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4" /><path d="M12 2v2" /><path d="M12 20v2" /><path d="m4.93 4.93 1.41 1.41" /><path d="m17.66 17.66 1.41 1.41" /><path d="M2 12h2" /><path d="M20 12h2" /><path d="m6.34 17.66-1.41 1.41" /><path d="m19.07 4.93-1.41 1.41" />
            </svg>
        </button>

        <!-- User Dropdown Menu -->
        @if ($user)
            <x-blatui-admin::ui.dropdown align="right" width="56">
                <x-slot:trigger>
                    <button type="button" class="flex items-center gap-2.5 rounded-lg p-1.5 transition hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                        <x-blatui-admin::ui.avatar size="sm" :name="$user->name ?: $user->username" :src="$user->avatar" />
                        <div class="hidden text-left md:block">
                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $user->name ?: $user->username }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                {{ $user->roles->pluck('name')->first() ?: 'Administrator' }}
                            </div>
                        </div>
                        <svg class="hidden h-4 w-4 text-gray-400 md:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                </x-slot:trigger>

                <div class="px-4 py-3">
                    <p class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $user->name ?: $user->username }}</p>
                    <p class="text-[11px] text-gray-500 truncate dark:text-gray-400">{{ $user->roles->pluck('name')->implode(', ') ?: 'Administrator' }}</p>
                </div>

                <div class="py-1">
                    <form method="POST" action="{{ \BlatUI\Admin\Admin::url('auth/logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/50 cursor-pointer">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" x2="9" y1="12" y2="12" />
                            </svg>
                            <span>{{ __('blatui-admin::admin.logout') ?? 'Logout' }}</span>
                        </button>
                    </form>
                </div>
            </x-blatui-admin::ui.dropdown>
        @endif
    </div>
</header>
