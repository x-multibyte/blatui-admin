@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white text-gray-950 shadow-xs dark:border-gray-800 dark:bg-gray-900 dark:text-gray-50']) }}>
    @if ($title || $description || isset($header))
        <div class="flex flex-col space-y-1.5 p-6 border-b border-gray-100 dark:border-gray-800">
            @if (isset($header))
                {{ $header }}
            @else
                @if ($title)
                    <h3 class="font-semibold leading-none tracking-tight text-base sm:text-lg text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                @endif
                @if ($description)
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                @endif
            @endif
        </div>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="flex items-center p-6 pt-0 border-t border-gray-100 dark:border-gray-800 mt-auto">
            {{ $footer }}
        </div>
    @endif
</div>
