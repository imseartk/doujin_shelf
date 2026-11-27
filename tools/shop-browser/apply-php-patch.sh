#!/bin/bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=l
target=8.3.6-0ubuntu0.24.04.13
test -s /var/backups/php-security-N0nHaGcP/config.tar.gz
mapfile -t packages < <(dpkg-query -W -f='${Package} ${Status}\n' 'php8.3-*' | awk '/ install ok installed$/ {print $1}')
args=()
for package in "${packages[@]}"; do
  installed=$(dpkg-query -W -f='${Version}' "$package")
  if dpkg --compare-versions "$installed" gt "$target"; then
    echo 'Newer version detected; refusing downgrade.' >&2
    exit 1
  fi
  args+=("$package=$target")
done
apt-get -y --no-remove --only-upgrade -o Dpkg::Options::=--force-confold install "${args[@]}"
php-fpm8.3 -t
nginx -t
systemctl restart php8.3-fpm
systemctl is-active php8.3-fpm nginx
for package in "${packages[@]}"; do
  version=$(dpkg-query -W -f='${Version}' "$package")
  dpkg --compare-versions "$version" ge "$target"
  printf '%s %s\n' "$package" "$version"
done
pid=$(systemctl show php8.3-fpm -p MainPID --value)
printf 'Running FPM executable: '
readlink "/proc/$pid/exe"
cmp "/proc/$pid/exe" /usr/sbin/php-fpm8.3
php -m | diff -u /var/backups/php-security-N0nHaGcP/php-modules.txt -
for host in doujin.artick.tw doujin-dev.artick.tw artick.tw; do
  code=$(curl --max-time 30 -sS -o /dev/null -w '%{http_code}' "https://$host/")
  printf '%s AFTER %s\n' "$host" "$code"
  test "$code" = 200
done
dpkg --audit
journalctl -u php8.3-fpm --since '3 minutes ago' -p err --no-pager
