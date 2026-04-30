#!/usr/bin/env bash
# shellcheck source=/dev/null
source <(curl -fsSL https://raw.githubusercontent.com/community-scripts/ProxmoxVE/main/misc/build.func)
# Copyright (c) 2021-2026 Steven Buehner
# Author: Steven Buehner
# License: MIT | https://github.com/stevenbuehner/php-ical-filter-proxy/raw/refs/heads/master/LICENSE
# Source: https://github.com/stevenbuehner/php-ical-filter-proxy

APP="PHP Ical Filter Proxy"
var_tags="${var_tags:-calendar;proxy;php}"
var_cpu="${var_cpu:-1}"
var_ram="${var_ram:-1024}"
var_disk="${var_disk:-8}"
var_os="${var_os:-debian}"
var_version="${var_version:-13}"
var_unprivileged="${var_unprivileged:-1}"

header_info "$APP"
variables
color
catch_errors

function update_script() {
  header_info "$APP"
  check_container_storage
  check_container_resources
  if [[ ! -d /opt/php-ical-filter-proxy ]]; then
    msg_error "No ${APP} Installation Found!"
    exit 1
  fi
  if [[ -z "${REPO_SLUG:-}" ]]; then
    REPO_SLUG="stevenbuehner/php-ical-filter-proxy"
  fi
  if check_for_gh_release "php-ical-filter-proxy" "$REPO_SLUG"; then
    msg_info "Updating ${APP} in Container"
    lxc-attach -n "$CTID" -- bash -lc '/opt/php-ical-filter-proxy/scripts/update.sh'
    msg_ok "Updated ${APP}"
  fi
  exit
}

start
build_container
description
msg_ok "Completed Successfully!\n"
echo -e "${CREATING}${GN}${APP} setup has been successfully initialized!${CL}"
echo -e "${INFO}${YW} Access it using the following URL:${CL}"
echo -e "${TAB}${GATEWAY}${BGN}http://${IP}${CL}"
