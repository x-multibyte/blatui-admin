<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-blatui-admin::ui.card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Administrators</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ \BlatUI\Admin\Models\Administrator::count() }}
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
            </div>
        </x-blatui-admin::ui.card>

        <x-blatui-admin::ui.card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Roles</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ \BlatUI\Admin\Models\Role::count() }}
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                    </svg>
                </div>
            </div>
        </x-blatui-admin::ui.card>

        <x-blatui-admin::ui.card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Permissions</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ \BlatUI\Admin\Models\Permission::count() }}
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="7.5" cy="15.5" r="5.5" /><path d="m21 2-9.6 9.6" /><path d="m15.5 7.5 3 3L22 7l-3-3" />
                    </svg>
                </div>
            </div>
        </x-blatui-admin::ui.card>

        <x-blatui-admin::ui.card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Menus</p>
                    <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ \BlatUI\Admin\Models\Menu::count() }}
                    </p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" x2="20" y1="12" y2="12" /><line x1="4" x2="20" y1="6" y2="6" /><line x1="4" x2="20" y1="18" y2="18" />
                    </svg>
                </div>
            </div>
        </x-blatui-admin::ui.card>
    </div>

    <x-blatui-admin::ui.card title="Welcome to BlatUI Admin" description="Modern administrative dashboard powered by Blade, Alpine.js, and Tailwind CSS v4">
        <div class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
            <p>
                BlatUI Admin provides a developer-friendly PHP Builder DSL paired with a reactivity engine built on native web standards. No jQuery, no Bootstrap, and no Livewire runtime overhead.
            </p>
            <div class="flex flex-wrap gap-2 pt-2">
                <x-blatui-admin::ui.badge variant="default">Laravel 12/13</x-blatui-admin::ui.badge>
                <x-blatui-admin::ui.badge variant="secondary">Tailwind CSS v4</x-blatui-admin::ui.badge>
                <x-blatui-admin::ui.badge variant="success">Alpine.js v3</x-blatui-admin::ui.badge>
                <x-blatui-admin::ui.badge variant="outline">PHP 8.3+</x-blatui-admin::ui.badge>
            </div>
        </div>
    </x-blatui-admin::ui.card>
</div>
