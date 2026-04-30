#!/usr/bin/env bash

# Copyright (c) 2021-2026 Steven Buehner
# Author: Steven Buehner
# License: MIT | https://github.com/stevenbuehner/php-ical-filter-proxy/raw/refs/heads/master/LICENSE
# Source: https://github.com/stevenbuehner/php-ical-filter-proxy

# shellcheck source=/dev/null
source /dev/stdin <<<"$FUNCTIONS_FILE_PATH"
color
verb_ip6
catch_errors
setting_up_container
network_check
update_os

APP="PHP Ical Filter Proxy"
APP_SLUG="php-ical-filter-proxy"
APP_USER="icalproxy"
APP_GROUP="icalproxy"
APP_DIR="/opt/${APP_SLUG}"
REPO_SLUG="stevenbuehner/php-ical-filter-proxy"
REPO_URL="https://github.com/${REPO_SLUG}.git"
DEPLOY_REF_MODE="stable-tag"
NGINX_SITE="/etc/nginx/sites-available/${APP_SLUG}.conf"
PHP_VERSION="8.4"
PHP_FPM_SERVICE="php8.4-fpm"

msg_info "Installing dependencies"
$STD apt-get install -y \
  ca-certificates \
  curl \
  git \
  unzip \
  nginx \
  composer \
  php8.4 \
  php8.4-fpm \
  php8.4-cli \
  php8.4-curl \
  php8.4-mbstring \
  php8.4-xml \
  php8.4-zip \
  php8.4-intl \
  php8.4-bcmath \
  php8.4-opcache
msg_ok "Installed dependencies"

if ! id -u "$APP_USER" >/dev/null 2>&1; then
  msg_info "Creating service user"
  useradd --system --create-home --shell /usr/sbin/nologin "$APP_USER"
  msg_ok "Created service user"
fi

msg_info "Fetching latest stable tag"
LATEST_TAG="$(git ls-remote --refs --tags "$REPO_URL" | awk '{print $2}' | sed 's#refs/tags/##' | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -n1)"
if [[ -z "$LATEST_TAG" ]]; then
  msg_error "No stable semver tag found in ${REPO_URL}"
  exit 1
fi
msg_ok "Selected tag ${LATEST_TAG}"

msg_info "Cloning application"
rm -rf "$APP_DIR"
git clone --depth 1 --branch "$LATEST_TAG" "$REPO_URL" "$APP_DIR"
msg_ok "Cloned application"

msg_info "Installing Composer dependencies"
cd "$APP_DIR" || exit 1
$STD composer install --no-dev --prefer-dist --optimize-autoloader
msg_ok "Composer dependencies installed"

if [[ ! -f "$APP_DIR/config/calendars.yaml" && -f "$APP_DIR/config/calendars.example.yaml" ]]; then
  msg_info "Provisioning default runtime config"
  cp "$APP_DIR/config/calendars.example.yaml" "$APP_DIR/config/calendars.yaml"
  msg_ok "Provisioned runtime config"
fi

mkdir -p "$APP_DIR/var/cache/feeds" "$APP_DIR/var/cache/exports" "$APP_DIR/var/log" "$APP_DIR/scripts"
chown -R "$APP_USER":"$APP_GROUP" "$APP_DIR"
chmod -R u=rwX,g=rX,o= "$APP_DIR"
chmod -R u=rwX,g=rwX,o= "$APP_DIR/var"

cat > "$APP_DIR/scripts/update.sh" <<'EOS'
#!/usr/bin/env bash
set -euo pipefail
APP_DIR="/opt/php-ical-filter-proxy"
APP_USER="icalproxy"
APP_GROUP="icalproxy"
REPO_URL="https://github.com/stevenbuehner/php-ical-filter-proxy.git"
DEPLOY_REF_MODE="${DEPLOY_REF_MODE:-stable-tag}"
PHP_VERSION="8.4"
PHP_FPM_SERVICE="php8.4-fpm"

cd "$APP_DIR"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y --no-install-recommends \
  ca-certificates curl git unzip nginx composer \
  php8.4 php8.4-fpm php8.4-cli php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-intl php8.4-bcmath php8.4-opcache
apt-get upgrade -y

if [[ "${DEPLOY_REF_MODE}" == "latest-release" ]]; then
  LATEST_REF="$(curl -fsSL https://api.github.com/repos/stevenbuehner/php-ical-filter-proxy/releases/latest | grep '"tag_name"' | head -1 | cut -d '"' -f4)"
else
  LATEST_REF="$(git ls-remote --refs --tags "$REPO_URL" | awk '{print $2}' | sed 's#refs/tags/##' | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -n1)"
fi
if [[ -z "$LATEST_REF" ]]; then
  echo "No target release found" >&2
  exit 1
fi

cp -f config/calendars.yaml /tmp/calendars.yaml.bak 2>/dev/null || true
git fetch --tags origin
git checkout -f "$LATEST_REF"
composer install --no-dev --prefer-dist --optimize-autoloader
mkdir -p var/cache/feeds var/cache/exports var/log
chown -R "$APP_USER":"$APP_GROUP" "$APP_DIR"
chmod -R u=rwX,g=rX,o= "$APP_DIR"
chmod -R u=rwX,g=rwX,o= "$APP_DIR/var"
cp -f /tmp/calendars.yaml.bak config/calendars.yaml 2>/dev/null || true
php bin/console app:config:validate >/dev/null 2>&1 || true
systemctl reload nginx >/dev/null 2>&1 || true
systemctl reload "$PHP_FPM_SERVICE" >/dev/null 2>&1 || true
EOS
chmod +x "$APP_DIR/scripts/update.sh"
sed -i "s/^DEPLOY_REF_MODE=.*/DEPLOY_REF_MODE=\"${DEPLOY_REF_MODE}\"/" "$APP_DIR/scripts/update.sh"

cat > "$NGINX_SITE" <<EOF_NGINX
server {
  listen 80;
  listen [::]:80;
  server_name _;
  root ${APP_DIR}/public;
  index index.php;

  location / {
    try_files \$uri /index.php\$is_args\$args;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
  }

  location ~ /\. {
    deny all;
  }
}
EOF_NGINX

ln -sf "$NGINX_SITE" "/etc/nginx/sites-enabled/${APP_SLUG}.conf"
rm -f /etc/nginx/sites-enabled/default

msg_info "Configuring php-fpm pool"
cat > "/etc/php/${PHP_VERSION}/fpm/pool.d/${APP_SLUG}.conf" <<EOF_FPM
[${APP_SLUG}]
user = ${APP_USER}
group = ${APP_GROUP}
listen = /run/php/php${PHP_VERSION}-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = ${APP_DIR}
EOF_FPM
msg_ok "Configured php-fpm"

systemctl enable -q --now "$PHP_FPM_SERVICE"
systemctl enable -q --now nginx
nginx -t
systemctl reload nginx

motd_ssh
customize
cleanup_lxc
msg_ok "${APP} installation completed"
