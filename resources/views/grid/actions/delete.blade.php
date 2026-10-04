<div x-data="{
    confirming: false,
    loading: false,
    performDelete() {
        if (this.loading) return;
        this.loading = true;
        const token = {{ $token }} || document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
        fetch({{ $url }}, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            }
        }).then(res => {
            if (res.ok) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: {{ $successMessage }}, type: 'success' } }));
                const tr = $el.closest('tr');
                if (tr) {
                    tr.remove();
                } else {
                    window.location.reload();
                }
            } else {
                res.json().then(data => {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Failed to delete record', type: 'error' } }));
                }).catch(() => {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Failed to delete record', type: 'error' } }));
                });
            }
        }).catch(() => {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Network error while deleting', type: 'error' } }));
        }).finally(() => {
            this.loading = false;
            this.confirming = false;
        });
    }
}" class="relative inline-block text-left">
    <button type="button" @click="confirming = true" class="inline-flex items-center gap-1 text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors cursor-pointer" title="{{ $title }}">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        <span>{{ $title }}</span>
    </button>
    <div x-show="confirming" @click.away="confirming = false" x-cloak class="absolute right-0 z-50 mt-1 w-52 rounded-md bg-white p-3 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-gray-700" style="display: none;">
        <p class="text-xs text-gray-700 dark:text-gray-300">{{ $confirmText }}</p>
        <div class="mt-2.5 flex justify-end gap-1.5">
            <button type="button" @click="confirming = false" class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 cursor-pointer">Cancel</button>
            <button type="button" @click="performDelete()" :disabled="loading" class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50 cursor-pointer">Confirm</button>
        </div>
    </div>
</div>