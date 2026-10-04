> **Status: COMPLETE (verified 2026-10-04)**
> **Authority:** This document defines the implementation specification for Authorization Middleware in `x-multibyte/blatui-admin`, strictly adhering to `AGENTS.md` and referencing `dcat-admin`.

# 权限中间件 (Authorization Middleware) — Implementation Specification

## 1. Objective

Implement the `Authorization Middleware` (`BlatUI\Admin\Http\Middleware\Permission`) in `blatui-admin`, referencing the mature design in `dcat-admin` (`Dcat\Admin\Http\Middleware\Permission` and `Dcat\Admin\Http\Auth\Permission`), and adapt it to BlatUI Admin's clean Laravel-native architecture.

Previously, `CLAUDE.md` and `AGENTS.md` noted:
> `- Authorization middleware (explicitly deferred)`

This change activates and implements authorization middleware, wiring `Permission::shouldPassThrough()`, RBAC permissions evaluation, whitelist checking, superuser bypass, route-level permission checking, and 403 rejection handling.

## 2. Reference Analysis: Dcat Admin vs BlatUI Admin

In `dcat-admin`:
- Middleware `Dcat\Admin\Http\Middleware\Permission`:
  - Skips check if unauthenticated (`! $user`), if disabled (`! config('admin.permission.enable')`), if whitelisted (`shouldPassThrough`), or if super administrator (`$user->isAdministrator()`).
  - Supports route-level middleware parameters: `admin.permission:check,...`, `admin.permission:allow,...`, `admin.permission:deny,...`, `admin.permission:free`.
  - Checks `$user->allPermissions()->first(fn($p) => $p->shouldPassThrough($request))`.
  - If no permission matches, delegates to error handler (`Checker::error()`): returns 403 JSON for AJAX/JSON requests, or renders 403 page for web requests.
- Configuration `config/admin.php`:
  - `permission.enable` (boolean, default true).
  - `permission.except` (array of routes/paths exempt from permission checking, such as `'/'`, `'auth/login'`, `'auth/logout'`).

In `blatui-admin`:
- `Administrator` implements `HasPermissions` trait (`roles()`, `permissions()`, `isAdministrator()`, `isRole()`, `inRoles()`, `allPermissions()`).
- `Permission` model has `shouldPassThrough(Request $request)`.
- `Authenticate` middleware handles guest redirection.
- Missing components:
  1. `BlatUI\Admin\Http\Middleware\Permission` middleware class.
  2. `config('blatui-admin.permission')` configuration schema (`enable`, `except`).
  3. Prefix-aware URL matching in `Permission::shouldPassThrough()` and whitelist matching.
  4. 403 denial response handling for web and JSON requests with localized messages (`lang/en/admin.php` and `lang/zh_CN/admin.php`).
  5. Route middleware alias `'admin.permission'` in `AdminServiceProvider` and attachment to admin authenticated route group in `routes/blatui-admin.php`.

## 3. Target Architecture & Components

### 3.1 Middleware: `BlatUI\Admin\Http\Middleware\Permission`
File: `src/Http/Middleware/Permission.php`
- `handle(Request $request, Closure $next, ...$args): Response`
  - If `! Admin::user()`: pass through `$next($request)` (Authenticate middleware will handle redirect).
  - If `! config('blatui-admin.permission.enable', true)`: pass through.
  - If `shouldPassThrough($request)`: pass through.
  - If `$user->isAdministrator()`: pass through.
  - If `$args` provided:
    - `free`: pass through.
    - `allow,role1,role2`: allow if `$user->inRoles($roles)`, else 403.
    - `deny,role1,role2`: deny if `$user->inRoles($roles)` (403), else pass through.
    - `check,perm1,perm2`: allow if `$user->allPermissions()->pluck('slug')->intersect($perms)->isNotEmpty()`, else 403.
  - Default RBAC path matching:
    - Iterate `$user->allPermissions()`, if any `$permission->shouldPassThrough($request)` returns true, pass through.
    - Otherwise, deny access with 403 response.

### 3.2 Deny Response:
- For AJAX/JSON request:
  - Return JSON response with HTTP 403 status:
    ```json
    {
      "status": false,
      "message": "Permission denied."
    }
    ```
- For standard web request:
  - Call `abort(403, trans('blatui-admin::admin.deny') ?: 'Permission denied.')`.

### 3.3 Path Matching & Prefix Handling:
- Paths in permissions table (e.g. seeded `/auth/users*` or `auth/users*`) and `permission.except` (e.g. `'/'`, `'auth/login'`) are relative to admin prefix.
- `shouldPassThrough` in both `Permission` model and `Middleware\Permission` must normalize paths to match both prefixed (`admin/auth/users`) and unprefixed (`auth/users`) forms, supporting wildcard `*` matching.

### 3.4 Configuration: `config/blatui-admin.php`
```php
'permission' => [
    'enable' => true,
    'except' => [
        '/',
        'auth/login',
        'auth/logout',
    ],
],
```

### 3.5 Routes & Service Provider Wiring:
- In `src/AdminServiceProvider.php`:
  - Register route middleware alias: `$router->aliasMiddleware('admin.permission', Permission::class)` (or `$this->app['router']->aliasMiddleware(...)`).
- In `routes/blatui-admin.php`:
  - Include `Permission::class` in the authenticated admin route group middleware array.

### 3.6 Translations:
- `lang/en/admin.php`: `'deny' => 'Permission denied.'`
- `lang/zh_CN/admin.php`: `'deny' => '无权限访问。'`

## 4. Test Cases Board (Acceptance Criteria)

| # | Test Case Description | Assertion Target |
|---|---|---|
| 1 | Guest user passing through Permission middleware is not blocked with 403 | `Permission::handle` guest check (`! $user`) |
| 2 | Global permission toggle disabled (`permission.enable = false`) allows any authenticated user | `Permission::handle` toggle check (`! config('blatui-admin.permission.enable')`) |
| 3 | Whitelisted routes in `permission.except` (e.g. `/admin`) allow any authenticated user | `Permission::shouldPassThrough` whitelist check |
| 4 | Super administrator (`isAdministrator() === true`) accesses any admin resource (200 OK) | `Permission::handle` superadmin check |
| 5 | Non-super admin with `users` permission accessing `/admin/auth/users` succeeds (200 OK) | `Permission::handle` RBAC matching + `Permission::shouldPassThrough` |
| 6 | Non-super admin with `users` permission accessing `/admin/auth/roles` is rejected with 403 | `Permission::deny` 403 response |
| 7 | AJAX/JSON request without permission receives 403 JSON (`status: false`, `message`) | `Permission::deny` JSON response logic |
| 8 | Route middleware parameter `admin.permission:free` bypasses checks for any authenticated user | `Permission::handle` parameter `free` branch |
| 9 | Route middleware parameter `admin.permission:allow,editor` allows only matching role | `Permission::handle` parameter `allow` branch |
| 10 | Route middleware parameter `admin.permission:deny,guest` rejects matching role with 403 | `Permission::handle` parameter `deny` branch |
| 11 | Route middleware parameter `admin.permission:check,users` allows only users with that slug | `Permission::handle` parameter `check` branch |
| 12 | Prefix handling: `http_path` `/auth/users*` matches request `/admin/auth/users` | `Permission::shouldPassThrough` prefix normalization |
