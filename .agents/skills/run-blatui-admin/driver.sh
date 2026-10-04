#!/usr/bin/env bash
set -euo pipefail

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SKILL_DIR}/../../.." && pwd)"
PID_FILE="${SKILL_DIR}/server.pid"
LOG_FILE="/tmp/blatui-admin-server.log"
PORT="${PORT:-8000}"
HOST="127.0.0.1"
BASE_URL="http://${HOST}:${PORT}"

# Locate agent-browser
AGENT_BROWSER="${AGENT_BROWSER:-}"
if [ -z "$AGENT_BROWSER" ]; then
    if command -v agent-browser >/dev/null 2>&1; then
        AGENT_BROWSER="agent-browser"
    elif [ -x "/home/linuxbrew/.linuxbrew/bin/agent-browser" ]; then
        AGENT_BROWSER="/home/linuxbrew/.linuxbrew/bin/agent-browser"
    else
        AGENT_BROWSER=""
    fi
fi

cd "$ROOT_DIR"

cmd_init() {
    echo "==> Ensuring workbench/.env exists..."
    if [ ! -f "workbench/.env" ] && [ -f "workbench/.env.example" ]; then
        cp workbench/.env.example workbench/.env
    fi
    if [ ! -f "vendor/orchestra/testbench-core/laravel/database/database.sqlite" ]; then
        echo "==> Building workbench database..."
        composer build
    fi
    echo "==> Initializing BlatUI Admin database and workbench..."
    composer exec testbench -- admin:install --env=local
    echo "==> Initialization complete. Default credentials: admin / admin"
}

cmd_start() {
    if cmd_status >/dev/null 2>&1; then
        echo "==> Server already running on ${BASE_URL}"
        return 0
    fi

    cmd_init

    echo "==> Starting testbench server on ${BASE_URL} (local env)..."
    nohup composer exec testbench -- serve --env=local --host="${HOST}" --port="${PORT}" > "${LOG_FILE}" 2>&1 &
    echo $! > "${PID_FILE}"

    echo "==> Waiting for server to become responsive..."
    local attempts=0
    local max_attempts=30
    until curl -sf "${BASE_URL}/admin/auth/login" >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ $attempts -ge $max_attempts ]; then
            echo "ERROR: Server failed to start within ${max_attempts}s. Server logs:"
            cat "${LOG_FILE}"
            return 1
        fi
        sleep 0.5
    done
    echo "==> Server is up and running at ${BASE_URL}"
}

cmd_stop() {
    echo "==> Stopping server..."
    if [ -f "${PID_FILE}" ]; then
        local pid
        pid=$(cat "${PID_FILE}")
        kill "$pid" 2>/dev/null || true
        rm -f "${PID_FILE}"
    fi
    if command -v fuser >/dev/null 2>&1; then
        fuser -k "${PORT}/tcp" 2>/dev/null || true
    elif command -v lsof >/dev/null 2>&1; then
        lsof -ti:"${PORT}" -sTCP:LISTEN | xargs -r kill 2>/dev/null || true
    fi
    echo "==> Server stopped."
}

cmd_status() {
    if curl -sf "${BASE_URL}/admin/auth/login" >/dev/null 2>&1; then
        echo "==> Server is UP on ${BASE_URL}"
        return 0
    else
        echo "==> Server is DOWN on ${BASE_URL}"
        return 1
    fi
}

cmd_logout() {
    if [ -z "$AGENT_BROWSER" ]; then
        echo "ERROR: agent-browser CLI not found."
        return 1
    fi
    cmd_start
    echo "==> Logging out..."
    "$AGENT_BROWSER" open "${BASE_URL}/admin/auth/logout"
    echo "==> Logged out."
}

cmd_login() {
    if [ -z "$AGENT_BROWSER" ]; then
        echo "ERROR: agent-browser CLI not found. Please install agent-browser."
        return 1
    fi

    cmd_start

    echo "==> Navigating to ${BASE_URL}/admin/auth/login..."
    "$AGENT_BROWSER" open "${BASE_URL}/admin/auth/login"

    local curr_url
    curr_url=$("$AGENT_BROWSER" get url 2>/dev/null || echo "")
    if [[ "$curr_url" == *"/admin" ]]; then
        echo "==> Already authenticated! Currently at: ${curr_url}"
        return 0
    fi

    echo "==> Filling credentials (admin/admin)..."
    "$AGENT_BROWSER" fill "input[name=\"username\"]" "admin"
    "$AGENT_BROWSER" fill "input[name=\"password\"]" "admin"
    "$AGENT_BROWSER" click "button[type=\"submit\"]"

    echo "==> Waiting for navigation to ${BASE_URL}/admin..."
    local attempts=0
    while [ $attempts -lt 15 ]; do
        curr_url=$("$AGENT_BROWSER" get url 2>/dev/null || echo "")
        if [[ "$curr_url" == *"/admin" ]]; then
            echo "==> Successfully authenticated! Current URL: ${curr_url}"
            return 0
        fi
        sleep 0.5
        attempts=$((attempts + 1))
    done

    echo "ERROR: Failed to reach /admin dashboard after login."
    return 1
}

cmd_screenshot() {
    local target="${1:-${ROOT_DIR}/dashboard.png}"
    if [ -z "$AGENT_BROWSER" ]; then
        echo "ERROR: agent-browser CLI not found."
        return 1
    fi

    echo "==> Capturing screenshot to ${target}..."
    "$AGENT_BROWSER" screenshot "${target}"
    echo "==> Screenshot captured: ${target}"
}

cmd_smoke() {
    echo "=========================================="
    echo "Running BlatUI Admin Smoke Test"
    echo "=========================================="
    cmd_init
    cmd_start
    cmd_logout
    cmd_login
    local shot_path="${ROOT_DIR}/dashboard.png"
    cmd_screenshot "${shot_path}"
    echo "=========================================="
    echo "SMOKE TEST PASSED! Screenshot: ${shot_path}"
    echo "=========================================="
}

case "${1:-smoke}" in
    init)
        cmd_init
        ;;
    start)
        cmd_start
        ;;
    stop)
        cmd_stop
        ;;
    status)
        cmd_status
        ;;
    logout)
        cmd_logout
        ;;
    login)
        cmd_login
        ;;
    screenshot)
        shift
        cmd_screenshot "${1:-${ROOT_DIR}/dashboard.png}"
        ;;
    smoke)
        cmd_smoke
        ;;
    *)
        echo "Usage: $0 {init|start|stop|status|logout|login|screenshot [path]|smoke}"
        exit 1
        ;;
esac
