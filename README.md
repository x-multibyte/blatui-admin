<div align="center">
    <h1>BlatUI Admin</h1>
    <p>An elegant, adaptive administration panel and lifecycle engine for Laravel, built on BlatUI.</p>
</div>

<p align="center">
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://img.shields.io/packagist/v/x-multibyte/blatui-admin.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://img.shields.io/packagist/php-v/x-multibyte/blatui-admin.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://badge.laravel.cloud/badge/x-multibyte/blatui-admin?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/x-multibyte/blatui-admin/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/x-multibyte/blatui-admin/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://img.shields.io/packagist/dt/x-multibyte/blatui-admin.svg?style=flat-square" alt="Total Downloads"></a>
</p>

## Requirements

- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0`
- **Node.js**: `>=18.0.0`
- **Frontend Stack**: Tailwind CSS v4+ (with Vite), Alpine.js / Livewire v3+

## Highlights

- **Adaptive Installation**: Automatically detects host packages, lockfiles (`bun`, `pnpm`, `yarn`, `npm`), and runtime environments.
- **Zero Duplicate-Alpine**: Seamlessly integrates with existing Livewire, Flux, or standalone Alpine.js setups without colliding runtimes.
- **W3C & Tailwind v4 Ready**: Additive CSS wiring that respects existing stylesheets and strict Lightning CSS `@import` ordering.
- **Code Ownership Model**: Distributes customizable Blade UI components directly into your application's `resources/views/components/ui/` directory.

## Installation

Install the package via Composer:

```bash
composer require x-multibyte/blatui-admin
```

Run the interactive installation wizard:

```bash
php artisan blatui:admin --install
```

> **Note**: `php artisan admin:install` is supported as a backwards-compatible alias.

### Previewing with Dry Run

To inspect environment diagnostics and review the planned installation actions without modifying any files:

```bash
php artisan blatui:admin --install --dry-run
```

### Installation Options

| Option | Description |
| :--- | :--- |
| `--install` | Triggers the automated installation wizard. |
| `--dry-run` | Inspects environment diagnostics and displays the execution plan without modifying files. |
| `--force` | Overwrites existing UI components and bypasses interactive confirmation prompts. |
| `--fresh` | Runs database migrations using `migrate:fresh` instead of standard `migrate`. |
| `--install-apex-charts` | Installs the optional ApexCharts client library (~150KB) and publishes chart resources. |

## Quick Start

Once the installation wizard finishes, build your frontend assets:

```bash
npm run build
# or: pnpm build / yarn build / bun run build
```

Your administrative panel and base UI components are ready to use. Component templates are published directly into `resources/views/components/ui/`, giving you complete ownership to customize markup, styles, and behavior.

## Manual Publishing

If you prefer manual asset publishing over the interactive wizard, you may publish all package assets at once:

```bash
php artisan vendor:publish --tag="blatui-admin"
```

Or publish individual assets as needed:

### Configuration

```bash
php artisan vendor:publish --tag="blatui-admin-config"
```

### Migrations

```bash
php artisan vendor:publish --tag="blatui-admin-migrations"
php artisan migrate
```

### Views & Translations

```bash
php artisan vendor:publish --tag="blatui-admin-views"
php artisan vendor:publish --tag="blatui-admin-lang"
```

### Public Assets

```bash
php artisan vendor:publish --tag="blatui-admin-assets"
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on recent releases.

## Contributing

Please review [CONTRIBUTING.md](.github/CONTRIBUTING.md) for contribution guidelines.

## Security

Please review [SECURITY.md](.github/SECURITY.md) for our vulnerability reporting policy.

## Credits

- [Roy Thia](https://github.com/x-multibyte)
- [All Contributors](../../contributors)

## License

BlatUI Admin is open-sourced software licensed under the [MIT License](LICENSE.md).
