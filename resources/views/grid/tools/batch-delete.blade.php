<button
    type="button"
    @click="
        if (!selectedRows || selectedRows.length === 0) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: {{ $noSelectedText }}, type: 'warning' } }));
            return;
        }
        if (confirm({{ $confirmText }})) {
            const token = {{ $token }} || document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            fetch({{ $url }}, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                    'X-HTTP-Method-Override': 'DELETE'
                },
                body: JSON.stringify({
                    _method: 'DELETE',
                    ids: selectedRows
                })
            }).then(res => {
                if (res.ok) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: {{ $successMessage }}, type: 'success' } }));
                    setTimeout(() => window.location.reload(), 300);
                } else {
                    res.json().then(data => {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Failed to delete selected records', type: 'error' } }));
                    }).catch(() => {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Failed to delete selected records', type: 'error' } }));
                    });
                }
            }).catch(() => {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Network error while deleting', type: 'error' } }));
            });
        }
    "
    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/50 rounded-sm cursor-pointer transition-colors"
>
    <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
    <span>{{ $title }}</span>
</button>