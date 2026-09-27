---
type: concept
title: Quickstart and Task-Routing Guide
description: Primary entry point, architectural overview, and task-oriented navigation index for developers and AI agents working on BlatUI Admin.
tags: [quickstart, overview, navigation, index, architecture]
verified:
  - by: openwiki/0.6.0
    at: 2026-09-27T09:35:07.392Z
sources:
  - id: openwiki-source-b0ba3a7e3e7401d7d430ad92
    resource: repo://composer.json
  - id: openwiki-source-ec5c5d87d1eb6e4d270dea98
    resource: repo://database/seeders/AdminTablesSeeder.php
  - id: openwiki-source-23775c3de52f3ab95a13cb8b
    resource: repo://README.md
  - id: openwiki-source-978d47f39be3c14f87fd267d
    resource: repo://src/Console/Commands/InstallCommand.php
generated: { by: "antigravity", at: "2026-09-27T09:35:07.392Z" }
---

# Quickstart and Task-Routing Guide

## Executive Summary

`x-multibyte/blatui-admin` is a modern administration panel package designed for Laravel applications. It synthesizes a declarative, fluent PHP builder DSL with a modern BLAT frontend stack (Blade, Laravel, Alpine.js, and Tailwind CSS v4).

The package operates as a standalone administrative layer that plugs into host Laravel applications without altering host authentication schemes, user models, or frontend asset build pipelines.

---

## Architectural Principles

1. **Fluent PHP Builder DSL**: Declarative controller APIs for constructing tabular data grids, form structures, and responsive layouts.
2. **Modern BLAT Frontend Stack**: Tailwind CSS v4 design tokens and Alpine.js reactive components replacing legacy jQuery, Pjax, and Bootstrap dependencies.
3. **Decoupled RBAC & Identity**: Dedicated administrative authentication guard (`admin`), isolated user and role tables, and dynamic permission resolution.
4. **Clean Persistence Abstraction**: Repository pattern decoupling presentation DSLs from underlying Eloquent database layers.
5. **Zero-Friction Host Integration**: Granular vendor publishing tags and automated setup via `php artisan admin:install`.

---

## Rapid Getting Started

### 1. Installation via Composer
```bash
composer require x-multibyte/blatui-admin
```

### 2. Automated Initialization
```bash
php artisan admin:install
```

This single command:
- Executes the database migrations for all 8 administrative tables.
- Seeds the initial super administrator user (`admin / admin`) and RBAC permissions.
- Publishes configuration to `config/blatui-admin.php` and route scaffolds to `routes/admin.php`.
- Ensures the public upload directory is provisioned.

### 3. Accessing the Portal
Navigate to `/admin` and authenticate with `admin` / `admin`.

---

## Task-Routing Guide

Find the authoritative wiki documentation for common development and maintenance tasks:

| Developer Task / Query | Primary Wiki Page | Key Responsibilities |
| :--- | :--- | :--- |
| **Understand service provider, configuration & route loading** | [`overview.md`](architecture/overview.md) | `AdminServiceProvider`, `config/blatui-admin.php`, `routes/blatui-admin.php`, publish tags |
| **Configure admin users, roles, permissions & menus** | [`auth-and-rbac.md`](architecture/auth-and-rbac.md) | `Administrator`, `Role`, `Permission`, `Menu`, `OperationLog`, `ModelTree` |
| **Customize admin layout, header, sidebar & Blade components** | [`layout-and-views.md`](architecture/layout-and-views.md) | `Content`, `Row`, `Column`, `Navbar`, `<x-ui.*>` Blade components, Alpine state |
| **Build data grids, add column displayers & filters** | [`grid-engine.md`](architecture/grid-engine.md) | `Grid`, `Column`, `Filter`, `Repository`, `EloquentRepository`, batch actions |
<!-- openwiki: broken internal link [../workflows/installation-and-operations.md] file "../workflows/installation-and-operations.md" does not exist. Fix the href or restore the target, then delete this comment. -->
| **Install, publish assets, seed databases & manage operations** | [`installation-and-operations.md`](../workflows/installation-and-operations.md) | `admin:install`, vendor publishing, asset symlinks, seeder execution |
<!-- openwiki: broken internal link [../testing/testing-and-quality-assurance.md] file "../testing/testing-and-quality-assurance.md" does not exist. Fix the href or restore the target, then delete this comment. -->
| **Run tests, add test cases & inspect architectural boundaries** | [`testing-and-quality-assurance.md`](../testing/testing-and-quality-assurance.md) | Pest test suite, Orchestra Testbench, `ArchTest`, PHPStan static analysis |

---

## Repository Directory Layout

```text
blatui-admin/
├── config/                 # Package configuration defaults (blatui-admin.php)
├── database/
│   ├── migrations/         # Relational schema migrations (8 core tables)
│   └── seeders/            # Idempotent default data seeder (AdminTablesSeeder)
├── lang/                   # Localization catalogues (en, zh_CN)
├── resources/
│   └── views/              # Blade templates (layouts, grid, auth, components/ui)
├── routes/                 # Administrative routing declarations (blatui-admin.php)
├── src/
│   ├── Console/            # Artisan commands (admin:install, admin:make)
│   ├── Contracts/          # Core interfaces (Repository, Tree)
│   ├── Grid/               # Grid DSL, Columns, Displayers, Filters, Actions
│   ├── Http/               # Controllers (Admin, Auth, Dashboard) & Middleware
│   ├── Layout/             # Layout builder (Content, Row, Column, Navbar, Menu)
│   ├── Models/             # Eloquent models (Administrator, Role, Permission, Menu)
│   ├── Repositories/       # Data repository implementations (EloquentRepository)
│   └── AdminServiceProvider.php
├── tests/                  # Pest test suites & Orchestra Testbench harness
└── openwiki/               # Repository OpenWiki documentation index
```

---

## Quality Assurance & Verification Commands

```bash
# Execute unit, feature, and architecture tests
vendor/bin/pest

# Check code formatting standards
composer lint:check

# Perform static analysis
composer analyse
```
