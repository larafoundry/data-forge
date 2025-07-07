#!/usr/bin/env bash
# ───────── Data-Forge · PHP 8.2 · No-DB ─────────
set -euo pipefail

APP_DIR="/workspace/data-forge"   # Đường dẫn repo
PHP_VER="8.2"

###############################################################################
# 1) PHP 8.2 + Composer
###############################################################################
apt-get update -qq
add-apt-repository -y ppa:ondrej/php >/dev/null
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
  php${PHP_VER} \
  php${PHP_VER}-{cli,intl,mbstring,xml,zip} \
  unzip curl git > /dev/null

# Trỏ `php` mặc định tới đúng version
update-alternatives --install /usr/bin/php php /usr/bin/php${PHP_VER} 82

# Cài Composer nếu chưa có
command -v composer >/dev/null || {
  EXPECTED=$(curl -fsSL https://composer.github.io/installer.sig)
  curl -fsSL https://getcomposer.org/installer -o /tmp/inst.php
  ACTUAL=$(sha384sum /tmp/inst.php | awk '{print $1}')
  [ "$EXPECTED" = "$ACTUAL" ] || { echo "❌  Composer installer checksum sai!"; exit 1; }
  php /tmp/inst.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm /tmp/inst.php
}

###############################################################################
# 2) Cài dependency & tools
###############################################################################
cd "$APP_DIR" || { echo "❌  Repo path không tồn tại: $APP_DIR"; exit 1; }

export COMPOSER_MEMORY_LIMIT=-1
composer install --no-interaction --prefer-dist --optimize-autoloader

###############################################################################
# 3) Chạy quality-tools & test suite (tuỳ thích)
###############################################################################
echo -e "\n🔍  PHPStan:"
composer phpstan || true      # Thoải mái bỏ qua lỗi nếu muốn

echo -e "\n🎨  Laravel Pint:"
composer pint --test || true

echo -e "\n🧪  PestPHP tests:"
composer test || true         # Có thể chưa viết test, nên không fail script

###############################################################################
# 4) Hoàn tất
###############################################################################
echo -e "\n🎉  Xong!  Hack code ngay tại $APP_DIR"