#!/bin/bash
set -euo pipefail
umask 077
backup=$(mktemp -d /var/backups/php-security-XXXXXXXX)
tar -czf "$backup/config.tar.gz" -C / etc/php etc/nginx
dpkg-query -W > "$backup/packages.txt"
php -m > "$backup/php-modules.txt"
printf 'BACKUP=%s\n' "$backup"
df -h / /var
nginx -t
php-fpm8.3 -t
for host in doujin.artick.tw doujin-dev.artick.tw artick.tw; do
  curl --max-time 20 -sS -o /dev/null -w "$host BEFORE %{http_code}\n" "https://$host/"
done
apt-get update -o APT::Update::Error-Mode=any
apt-cache policy php8.3-fpm php8.3-common php8.3-cli
mapfile -t packages < <(dpkg-query -W -f='${Package} ${Status}\n' 'php8.3-*' | awk '/ install ok installed$/ {print $1}')
apt-get -s --no-remove --only-upgrade install "${packages[@]}"
