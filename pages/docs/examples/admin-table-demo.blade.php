<div class="w-full max-w-3xl">
    <x-ui.table variant="card">
        <x-ui.table-header>
            <x-ui.table-row>
                <x-ui.table-head>Diagnostic Check</x-ui.table-head>
                <x-ui.table-head>Detected Stack</x-ui.table-head>
                <x-ui.table-head>Wiring Mode</x-ui.table-head>
                <x-ui.table-head class="text-right">Status</x-ui.table-head>
            </x-ui.table-row>
        </x-ui.table-header>
        <x-ui.table-body>
            <x-ui.table-row>
                <x-ui.table-cell class="font-medium">Alpine Runtime</x-ui.table-cell>
                <x-ui.table-cell>Livewire v3 + Flux UI</x-ui.table-cell>
                <x-ui.table-cell><code class="text-xs">registerBlatUI(window.Alpine, &#123; theme: false &#125;)</code></x-ui.table-cell>
                <x-ui.table-cell class="text-right">
                    <x-ui.badge tone="success">Collision-Free</x-ui.badge>
                </x-ui.table-cell>
            </x-ui.table-row>
            <x-ui.table-row>
                <x-ui.table-cell class="font-medium">Stylesheet Import</x-ui.table-cell>
                <x-ui.table-cell>Tailwind CSS v4 (Vite)</x-ui.table-cell>
                <x-ui.table-cell><code class="text-xs">@import "./blatui.css";</code></x-ui.table-cell>
                <x-ui.table-cell class="text-right">
                    <x-ui.badge tone="info">Additive W3C</x-ui.badge>
                </x-ui.table-cell>
            </x-ui.table-row>
            <x-ui.table-row>
                <x-ui.table-cell class="font-medium">UI Components</x-ui.table-cell>
                <x-ui.table-cell>resources/views/components/ui</x-ui.table-cell>
                <x-ui.table-cell><code class="text-xs">button, card, input, dropdown-menu, table, badge, dialog</code></x-ui.table-cell>
                <x-ui.table-cell class="text-right">
                    <x-ui.badge tone="success">Owned</x-ui.badge>
                </x-ui.table-cell>
            </x-ui.table-row>
        </x-ui.table-body>
    </x-ui.table>
</div>
