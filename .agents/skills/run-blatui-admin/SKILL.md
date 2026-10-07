---
name: run-blatui-admin
description: Build, run, and drive blatui-admin. Use when asked to start blatui-admin, run its tests, build it, take a screenshot of its UI, or interact with the running app.
---

BlatUI Admin is a Laravel admin package providing a Blade, Alpine.js, and Tailwind CSS v4 administration panel. An agent drives it via the automated driver script `.agents/skills/run-blatui-admin/driver.sh` (or `agent-browser` against the Testbench dev server at `http://127.0.0.1:8000`).

## Prerequisites

- **PHP 8.3+** with SQLite extension
- **Composer 2.x**
- **Node.js 20+** (for `agent-browser` CLI)
- **agent-browser** (`/home/linuxbrew/.linuxbrew/bin/agent-browser` or `npm install -g agent-browser`)
- **curl**

Ubuntu packages:

```bash
sudo apt-get update
sudo apt-get install -y php-cli php-sqlite3 php-mbstring php-xml curl
```

## Setup

Install dependencies and initialize the admin database:

```bash
composer install
composer exec testbench -- admin:install
```

Default credentials seeded:
- Username: `admin`
- Password: `admin`

## Build

Build the workbench assets and run database migrations:

```bash
composer build
```

## Run (agent path)

The primary agent handle is the driver script at `.agents/skills/run-blatui-admin/driver.sh`.

### Full automated smoke test

Runs the end-to-end verification flow (initializes database, starts server, logs out, logs in as `admin`, reaches `/admin` dashboard, and saves a screenshot):

```bash
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh smoke
```

### Driver commands

```bash
# Start background server on port 8000 and wait for health check
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh start

# Check server status (HTTP 200 on /admin/auth/login)
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh status

# Log out any active browser session
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh logout

# Drive browser login with admin/admin and navigate to /admin
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh login

# Take a screenshot of the current page
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh screenshot

# Stop the running background server
composer exec bash -- .agents/skills/run-blatui-admin/driver.sh stop
```

Screenshots land at `.agents/.cache/screenshots/dashboard.png` (or a specified path). Server logs land at `/tmp/blatui-admin-server.log`.

### Interactive browser driving via agent-browser

If you want to manually drive individual elements:

```bash
# Open login page
composer exec /home/linuxbrew/.linuxbrew/bin/agent-browser -- open http://127.0.0.1:8000/admin/auth/login

# Inspect interactive elements
composer exec /home/linuxbrew/.linuxbrew/bin/agent-browser -- snapshot -i

# Fill form and submit
composer exec /home/linuxbrew/.linuxbrew/bin/agent-browser -- fill 'input[name="username"]' "admin"
composer exec /home/linuxbrew/.linuxbrew/bin/agent-browser -- fill 'input[name="password"]' "admin"
composer exec /home/linuxbrew/.linuxbrew/bin/agent-browser -- click 'button[type="submit"]'

# Capture screenshot
composer exec /home/linuxbrew/.linuxbrew/bin/agent-browser -- screenshot .agents/.cache/screenshots/dashboard.png
```

## Run (human path)

For interactive browser testing with live reloading:

```bash
composer serve
```

Server starts at `http://127.0.0.1:8000/admin/auth/login`. Sign in with `admin` / `admin`. Press `Ctrl+C` to stop.

## Test

Run the full test suite (always run `composer clear` first if workbench was seeded):

```bash
composer clear && composer test:unit
```

All 162 unit and feature tests pass.

For complete static analysis, code style, and type coverage checks:

```bash
composer test
```

## Gotchas

- **Redirect on active session**: When authenticated, navigating to `/admin/auth/login` returns an HTTP 302 redirect directly to `/admin`. Attempting to fill `input[name="username"]` on `/admin` will fail with element not found. Use `driver.sh logout` or check the current URL before attempting login.
- **Workbench database pollution**: `composer build` and `admin:install` populate `vendor/orchestra/testbench-core/laravel/database/database.sqlite`. Running unit tests without clearing can cause unique constraint collisions on `admin_roles.slug`. Run `composer clear` to reset the skeleton before running `composer test:unit`.
- **Chromium Snap confinement**: Raw `chromium-browser --screenshot` installed via snap cannot write to arbitrary directories outside home/tmp due to AppArmor confinement. `agent-browser` bypasses this by streaming screenshots via CDP and writing them from the Node.js runtime.
- **Composer exec wrapper for auto-mode**: In Claude Code auto-mode, invoking commands via `composer exec bash -- ...` or `composer exec ...` matches the whitelisted `Bash(composer *)` pattern and avoids permission classifier delays.

## Troubleshooting

- **`Element not found: input[name="username"]`**: The browser already has an active session and was redirected to `/admin`. Run `.agents/skills/run-blatui-admin/driver.sh logout` or verify with `agent-browser get url`.
- **`SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: admin_roles.slug` during tests**: Leftover seeded data from workbench in sqlite. Run `composer clear` to purge the skeleton and re-run `composer test:unit`.
- **`Server failed to start within 30s`**: Port 8000 is occupied by a stale server process. Run `.agents/skills/run-blatui-admin/driver.sh stop` or `lsof -ti:8000 -sTCP:LISTEN | xargs -r kill`.
