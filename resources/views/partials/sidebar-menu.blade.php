@props([
    'tree' => [],
])

<nav class="space-y-1 px-3 py-2">
    @foreach ($tree as $item)
        @php
            $hasChildren = ! empty($item['children']);
            $isActive = (bool) ($item['active'] ?? false);
        @endphp

        @if ($hasChildren)
            <div x-data="{ open: {{ $isActive ? 'true' : 'false' }} }" class="space-y-1">
                <button
                    type="button"
                    @click="open = !open"
                    class="group flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition-colors cursor-pointer {{ $isActive ? 'bg-gray-100 text-gray-950 dark:bg-gray-800/60 dark:text-gray-50' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
                >
                    <div class="flex items-center gap-3">
                        @include('blatui-admin::partials.menu-icon', ['icon' => $item['icon'] ?? ''])
                        <span>{{ $item['title'] }}</span>
                    </div>
                    <svg
                        class="h-4 w-4 shrink-0 transition-transform duration-200 text-gray-400 dark:text-gray-500"
                        :class="{ 'rotate-90': open }"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="pl-7 space-y-1"
                    style="{{ $isActive ? '' : 'display: none;' }}"
                >
                    @foreach ($item['children'] as $child)
                        @php
                            $childActive = (bool) ($child['active'] ?? false);
                        @endphp
                        <a
                            href="{{ $child['url'] }}"
                            class="group flex items-center gap-2.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ $childActive ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
                        >
                            @include('blatui-admin::partials.menu-icon', ['icon' => $child['icon'] ?? '', 'size' => 'sm'])
                            <span>{{ $child['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <a
                href="{{ $item['url'] }}"
                class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $isActive ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
            >
                @include('blatui-admin::partials.menu-icon', ['icon' => $item['icon'] ?? ''])
                <span>{{ $item['title'] }}</span>
            </a>
        @endif
    @endforeach
</nav>
