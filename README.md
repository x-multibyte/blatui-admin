<div align="center">
    <h1>BlatUI Admin</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://img.shields.io/packagist/v/x-multibyte/blatui-admin.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://img.shields.io/packagist/php-v/x-multibyte/blatui-admin.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://badge.laravel.cloud/badge/x-multibyte/blatui-admin?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/x-multibyte/blatui-admin/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/x-multibyte/blatui-admin/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/x-multibyte/blatui-admin"><img src="https://img.shields.io/packagist/dt/x-multibyte/blatui-admin.svg?style=flat-square" alt="Total Downloads"></a>
</p>

BlatUI Admin for Laravel projects.

## Installation

You can install the package via Composer:

```bash
composer require x-multibyte/blatui-admin
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="blatui-admin"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="blatui-admin-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="blatui-admin-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="blatui-admin-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="blatui-admin-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="blatui-admin-assets"
```

## Artisan Commands

### Listing the available commands

```bash
php artisan admin
php artisan admin:list
```

Both print the package banner, the installed version, and every registered `admin:*` command. The list is discovered at runtime, so commands registered by other packages appear here too.

### Publishing resources

`admin:publish` wraps `vendor:publish` with the package's tags. Pass the resources you want:

```bash
php artisan admin:publish --config --views --force
```

Run it with no options to pick the resources interactively:

```bash
php artisan admin:publish
```

| Option | Publishes |
| :--- | :--- |
| `--config` | `blatui-admin-config` |
| `--migrations` | `blatui-admin-migrations` |
| `--views` | `blatui-admin-views` |
| `--lang` | `blatui-admin-lang` |
| `--assets` | `blatui-admin-assets` |
| `--all` | `blatui-admin` (everything above) |
| `--force` | Overwrite existing files without asking |

Routes and seeders are not offered here; publish them directly if you need them:

```bash
php artisan vendor:publish --tag="blatui-admin-routes"
php artisan vendor:publish --tag="blatui-admin-seeders"
```

### Uninstalling

```bash
php artisan admin:uninstall
```

This rolls back the package's migrations and deletes every published resource — the config file, `routes/admin.php`, and the views, lang, and asset directories. It asks for confirmation first; `--force` skips the prompt.

Files under `App\Admin\` are your own application code and are never touched.

## Usage

The four bundled resource routes are available at:
- `/admin/auth/users`
- `/admin/auth/roles`
- `/admin/auth/permissions`
- `/admin/auth/menu`

You can swap a controller without forking the package by updating `config('blatui-admin.resources')`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to BlatUI Admin! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Roy Thia](https://github.com/x-multibyte)
- [All Contributors](../../contributors)

## License

BlatUI Admin is open-sourced software licensed under the [MIT license](LICENSE.md).
