<aside class="flex h-full flex-col justify-between border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <div>
        <!-- Logo / Branding -->
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 px-6 dark:border-gray-800">
            <a href="{{ \BlatUI\Admin\Admin::url('/') }}" class="flex items-center gap-2.5 font-bold text-gray-900 dark:text-gray-100">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white font-black text-base shadow-sm">
                    B
                </div>
                <span class="text-base tracking-tight font-semibold">{{ \BlatUI\Admin\Admin::title() }}</span>
            </a>
        </div>

        <!-- Navigation Menu -->
        <div class="py-4 overflow-y-auto">
            @php
                $menu = new \BlatUI\Admin\Layout\Menu();
                $tree = $menu->toTree();
            @endphp
            @include('blatui-admin::partials.sidebar-menu', ['tree' => $tree])
        </div>
    </div>

    <!-- Sidebar Footer / Version Info -->
    <div class="border-t border-gray-200 p-4 dark:border-gray-800">
        <div class="flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
            <span>BlatUI Admin</span>
            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-mono dark:bg-gray-800">v0.1.0</span>
        </div>
    </div>
</aside>
