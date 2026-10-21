---
name: blatui-admin-development
description: >
  Guides installation, component usage, architecture, and customization for BlatUI Admin in Laravel applications.
license: MIT
metadata:
  author: Roy Thia
---

# BlatUI Admin Development

Use this skill when installing, configuring, extending, or maintaining a Laravel application using `x-multibyte/blatui-admin`.

## Primary Goal

Configure and apply `x-multibyte/blatui-admin` with zero collisions, additive styling, and strict adherence to the code ownership model.

## Workflow

### 1. Installation & Environment Check

- Run `php artisan blatui:admin --install` to execute the adaptive installation wizard.
- Use `php artisan blatui:admin --install --dry-run` to preview environment diagnostics without modifying files.
- Remember: `php artisan admin:install` is supported as a backwards-compatible alias.

### 2. Component Management (Code Ownership)

- Add new components with `php artisan blatui:add <component>`.
- Components are published to `resources/views/components/ui/` where the application owns and customizes them.
- Always use the `<x-ui.*>` namespace in Blade views rather than hand-crafting complex markup.

### 3. Frontend Bundling

- After installing or modifying components, compile assets via:
  ```bash
  npm run build
  # or: bun run build / pnpm build / yarn build
  ```

## Invariants & Guardrails

- **Zero Duplicate-Alpine**: NEVER install `alpinejs` when Livewire or Flux is present in the host application.
- **Additive CSS/JS**: NEVER overwrite host `resources/css/app.css` or `resources/js/app.js`.
- **Pure Prompts**: Use `laravel/prompts` exclusively for CLI interactions. Never call `Command::$components`.
- **Opt-in ApexCharts**: ApexCharts is strictly opt-in (`--install-apex-charts`).

## Examples

### Using BlatUI Components in Admin Views

```blade
<x-ui.card>
    <x-ui.card-header>
        <x-ui.card-title>Dashboard</x-ui.card-title>
        <x-ui.card-description>Overview of recent activity</x-ui.card-description>
    </x-ui.card-header>
    <x-ui.card-content>
        <x-ui.button variant="primary">Create User</x-ui.button>
    </x-ui.card-content>
</x-ui.card>
```
