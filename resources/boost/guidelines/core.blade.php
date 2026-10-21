## BlatUI Admin

BlatUI Admin (`x-multibyte/blatui-admin`) is an administration panel and lifecycle engine for Laravel, built on BlatUI (Blade, Laravel, Alpine.js, Tailwind CSS v4).

### Key Architectural Invariants

- **Adaptive Installation**: Always use `php artisan blatui:admin --install` (or `--dry-run` to preview).
- **Zero Duplicate-Alpine**: Never install or bundle a standalone `alpinejs` when Livewire or Flux is already present in the host application.
- **Additive CSS/JS**: Never overwrite host `resources/css/app.css` or `resources/js/app.js`. Always use additive `@import` or script declarations following W3C / Lightning CSS order.
- **Code Ownership Model**: UI components live in `resources/views/components/ui/`. Components can be added or customized directly using `php artisan blatui:add <component>`.
- **Pure Prompts UI**: Console interactions strictly use `laravel/prompts`. Never use `Command::$components`.
- **Opt-in Heavy Dependencies**: ApexCharts is strictly opt-in (`--install-apex-charts`).

### Essential Commands

@verbatim
<code-snippet name="BlatUI Admin CLI Commands" lang="shell">
# Run the adaptive installer wizard
php artisan blatui:admin --install

# Dry-run preview of environment checks and actions
php artisan blatui:admin --install --dry-run

# Add BlatUI components to the admin panel
php artisan blatui:add <component>

# Update Boost AI context (loads BlatUI & BlatUI Admin guidelines)
php artisan boost:update --discover
</code-snippet>
@endverbatim
