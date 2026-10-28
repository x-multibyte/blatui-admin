# Getting Started

`x-multibyte/blatui-admin` is an adaptive administration panel and lifecycle installer engine for Laravel 12 & 13 built on top of [BlatUI](https://blatui.remix-it.com).

## Requirements

- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0`
- **Node.js**: `>=18.0.0` (supports `bun`, `pnpm`, `yarn`, and `npm`)
- **Frontend Stack**: Tailwind CSS v4+ with Vite, Alpine.js or Livewire v3/v4

## Installation

Require the package via Composer:

```bash
composer require x-multibyte/blatui-admin
```

Then launch the interactive installation wizard:

```bash
php artisan blatui:admin --install
```

::: tip Backwards-Compatible Alias
`php artisan admin:install` is registered as a compatibility proxy and forwards all options directly to `blatui:admin --install`.
:::

## Dry-Run Inspection

Before modifying any files in your repository, you can inspect the environment diagnostic table and preview every planned Composer, Node, Vite, and Artisan step:

```bash
php artisan blatui:admin --install --dry-run
```

## Command Options

| Option | Description |
| :--- | :--- |
| `--install` | Run the interactive installation wizard. |
| `--dry-run` | Output the environment-aware installation plan without making changes. |
| `--force` | Force reinstallation, bypass confirmation prompts, and overwrite existing assets. |
| `--fresh` | Drop all tables and re-run migrations from scratch (`migrate:fresh`). |
| `--composer-path=` | Custom path to the Composer binary when running Composer commands. |
| `--install-apex-charts` | Automatically install optional `apexcharts` without interactive prompting. |
| `--components=*` | Specific BlatUI components to install (defaults to core admin components). |

## Default Admin UI Components

By default, `StubManager::defaultAdminComponents()` installs seven core BlatUI components directly into `resources/views/components/ui/` so your application owns the markup:

- `button`
- `card`
- `input`
- `dropdown-menu`
- `table`
- `badge`
- `dialog`

### Live Component Preview: Buttons

Below is a live preview of `<x-ui.button>` rendered by the package's Orchestra Testbench compiler:

<BladePreview
  name="button-demo"
  title="Button Variants"
  command="php artisan blatui:admin --install --components=button"
  :min-height="140"
/>

## Manual Asset Publishing

If you prefer to publish package resources manually instead of running the full installation pipeline:

```bash
# Publish all BlatUI Admin resources at once
php artisan vendor:publish --tag="blatui-admin"

# Or publish specific resource groups
php artisan vendor:publish --tag="blatui-admin-config"
php artisan vendor:publish --tag="blatui-admin-views"
php artisan vendor:publish --tag="blatui-admin-lang"
php artisan vendor:publish --tag="blatui-admin-assets"
php artisan vendor:publish --tag="blatui-admin-migrations"
```
