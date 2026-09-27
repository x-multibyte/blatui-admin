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

## Usage

<!-- Add a basic usage example here. -->

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
