@props([
    'position' => 'top-right', // top-right | top-left | bottom-right | bottom-left
])

@php
$positionClasses = match ($position) {
    'top-left' => 'top-4 left-4 items-start',
    'bottom-right' => 'bottom-4 right-4 items-end',
    'bottom-left' => 'bottom-4 left-4 items-start',
    default => 'top-4 right-4 items-end',
};
@endphp

<div
    x-data="{
        toasts: [],
        add(toast) {
            const id = Date.now() + Math.random();
            const item = {
                id,
                message: toast.message || '',
                description: toast.description || '',
                type: toast.type || 'info', // success, error, warning, info
                timeout: toast.timeout || 4000
            };
            this.toasts.push(item);
            if (item.timeout > 0) {
                setTimeout(() => this.remove(id), item.timeout);
            }
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }"
    x-init="
        window.addEventListener('toast', (e) => add(e.detail));
        @if (session('success'))
            add({ message: {{ Js::from(session('success')) }}, type: 'success' });
        @endif
        @if (session('error'))
            add({ message: {{ Js::from(session('error')) }}, type: 'error' });
        @endif
        @if (session('warning'))
            add({ message: {{ Js::from(session('warning')) }}, type: 'warning' });
        @endif
        @if (session('info'))
            add({ message: {{ Js::from(session('info')) }}, type: 'info' });
        @endif
    "
    class="fixed z-50 flex flex-col gap-2 pointer-events-none {{ $positionClasses }}"
    style="min-width: 320px; max-width: 420px;"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            class="pointer-events-auto flex w-full items-start gap-3 rounded-lg border p-4 shadow-lg transition-all"
            :class="{
                'bg-white text-gray-900 border-gray-200 dark:bg-gray-900 dark:text-gray-100 dark:border-gray-800': toast.type === 'info',
                'bg-emerald-50 text-emerald-950 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:border-emerald-800': toast.type === 'success',
                'bg-red-50 text-red-950 border-red-200 dark:bg-red-950/50 dark:text-red-200 dark:border-red-800': toast.type === 'error',
                'bg-amber-50 text-amber-950 border-amber-200 dark:bg-amber-950/50 dark:text-amber-200 dark:border-amber-800': toast.type === 'warning'
            }"
        >
            <!-- Icon -->
            <div class="shrink-0 mt-0.5">
                <template x-if="toast.type === 'success'">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="h-5 w-5 text-red-600 dark:text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                    </svg>
                </template>
            </div>

            <!-- Content -->
            <div class="flex-1 text-sm">
                <div class="font-medium" x-text="toast.message"></div>
                <template x-if="toast.description">
                    <div class="mt-1 text-xs opacity-90" x-text="toast.description"></div>
                </template>
            </div>

            <!-- Close button -->
            <button
                type="button"
                @click="remove(toast.id)"
                class="shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 focus:outline-none focus:ring-1 focus:ring-gray-400"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
