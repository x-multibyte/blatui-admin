@props([
    'src' => null,
    'alt' => '',
    'name' => null,
    'size' => 'default', // sm | default | lg
])

@php
$sizeClasses = match ($size) {
    'sm' => 'h-8 w-8 text-xs',
    'lg' => 'h-14 w-14 text-base',
    default => 'h-10 w-10 text-sm',
};

$initials = '';
if ($name) {
    $words = preg_split('/\s+/', trim((string) $name));
    if ($words !== false && count($words) > 0) {
        if (count($words) >= 2) {
            $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        } else {
            $initials = mb_strtoupper(mb_substr($words[0], 0, 2));
        }
    }
}
@endphp

<div {{ $attributes->merge(['class' => "relative flex shrink-0 overflow-hidden rounded-full font-medium select-none items-center justify-center bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 $sizeClasses"]) }}>
    @if ($src)
        <img class="aspect-square h-full w-full object-cover" src="{{ $src }}" alt="{{ $alt ?: $name }}" />
    @else
        <span>{{ $initials ?: '?' }}</span>
    @endif
</div>
