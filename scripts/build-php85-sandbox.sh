#!/usr/bin/env bash
#
# build-php85-sandbox.sh
# ------------------------------------------------------------------
# 在 Debian 12 (bookworm) 沙箱中构建 PHP 8.5 CLI 开发环境。
# 依赖 sury.org 官方 APT 源（php8.5-cli 候选版本: 8.5.x）。
#
# 用法:
#   sudo bash scripts/build-php85-sandbox.sh            # 安装并切换默认 php 为 8.5
#   sudo bash scripts/build-php85-sandbox.sh --no-switch # 仅安装，不改 update-alternatives
#   SKIP_COMPOSER=1 sudo bash scripts/build-php85-sandbox.sh  # 跳过 Composer 步骤
# ------------------------------------------------------------------
set -euo pipefail

PHP_VERSION="8.5"
SWITCH_DEFAULT=true
[[ "${1:-}" == "--no-switch" ]] && SWITCH_DEFAULT=false

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }

if [[ $EUID -ne 0 ]]; then
    echo "请以 root 运行: sudo bash $0" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# 1. 基础工具 + GPG -----------------------------------------------------------
log "安装基础依赖 (curl ca-certificates gnupg lsb-release)"
apt-get update -qq
apt-get install -y -qq curl ca-certificates gnupg lsb-release apt-transport-https >/dev/null

# 2. 配置 sury.org APT 源（若已存在则跳过） -----------------------------------
if ! grep -rq "packages.sury.org/php" /etc/apt/sources.list.d/ 2>/dev/null; then
    log "添加 sury.org PHP APT 源"
    # sury 官方文档推荐直接下载已 dearmor 的 apt.gpg；
    # gpg --dearmor 方式需要 --batch --yes 以避免文件已存在时交互阻塞。
    curl -fsSL https://packages.sury.org/php/apt.gpg \
        -o /etc/apt/trusted.gpg.d/sury-php.gpg
    echo "deb [signed-by=/etc/apt/trusted.gpg.d/sury-php.gpg] https://packages.sury.org/php $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/sury-php.list
else
    log "sury.org 源已存在，跳过添加"
fi
apt-get update -qq

# 3. 安装 PHP 8.5 CLI 及 Laravel 项目常用扩展 ----------------------------------
log "安装 php${PHP_VERSION}-cli 与常用扩展"
# opcache 已编译进 php8.5-cli 本体（Zend OPcache），sury 无独立 php8.5-opcache 包
EXTENSIONS=(mbstring xml curl zip intl mysql pgsql sqlite3 gd bcmath readline)
PKGS=("php${PHP_VERSION}-cli")
for ext in "${EXTENSIONS[@]}"; do
    PKGS+=("php${PHP_VERSION}-${ext}")
done
apt-get install -y -qq "${PKGS[@]}" >/dev/null || {
    # 个别扩展包名在 sury 上可能缺失时，逐个回退安装，避免整体失败
    log "部分扩展安装失败，改为逐个安装"
    for pkg in "${PKGS[@]}"; do
        apt-get install -y -qq "$pkg" >/dev/null 2>&1 || echo "  !! 跳过不可用包: $pkg"
    done
}

# 4. 切换默认 php 指向 8.5 -----------------------------------------------------
if [[ "$SWITCH_DEFAULT" == true ]]; then
    log "通过 update-alternatives 将默认 php 切换为 ${PHP_VERSION}"
    update-alternatives --install /usr/bin/php php "/usr/bin/php${PHP_VERSION}" 100
    update-alternatives --set php "/usr/bin/php${PHP_VERSION}"
fi

# 5. 调整 CLI 配置（内存限制等，供 composer/测试使用） --------------------------
log "写入 CLI 调优配置"
cat > "/etc/php/${PHP_VERSION}/cli/conf.d/99-sandbox.ini" <<'INI'
memory_limit = 512M
max_execution_time = 300
error_reporting = E_ALL
display_errors = On
date.timezone = UTC
INI

# 6. 安装/更新 Composer（可用 SKIP_COMPOSER=1 跳过） ---------------------------
if [[ "${SKIP_COMPOSER:-0}" != "1" ]]; then
    log "安装/更新 Composer"
    if command -v composer >/dev/null 2>&1; then
        composer self-update 2>/dev/null || composer --version
    else
        curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
        php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm -f /tmp/composer-setup.php
    fi
fi

# 7. 验证 ----------------------------------------------------------------------
log "验证环境"
php -v
php -m | tr '\n' ' '; echo
command -v composer >/dev/null && composer --version || true

log "完成 ✅  PHP $(php -r 'echo PHP_VERSION;') 已就绪"
