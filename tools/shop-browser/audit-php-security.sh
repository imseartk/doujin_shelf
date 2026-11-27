#!/bin/bash
# Read-only inventory; no configuration changes or package installation.
set -eu
printf '\nOS\n'
cat /etc/os-release
printf '\nPHP packages\n'
dpkg-query -W -f='${Package} ${Version} ${Status}\n' 'php*' 2>/dev/null | grep ' installed$' || true
printf '\nPHP CLI\n'
php -v
printf '\nFPM binaries\n'
for binary in /usr/sbin/php-fpm*; do
    [ -x "$binary" ] && "$binary" -v
done
printf '\nPackage candidates (existing cache only)\n'
apt-cache policy php8.3-fpm php8.3-common php8.3-cli
printf '\nRunning FPM\n'
systemctl list-units --type=service --state=running --no-pager 'php*-fpm.service'
printf '\nNginx PHP routing\n'
nginx -T 2>/dev/null | grep -E 'server_name |fastcgi_pass |root /var/www|listen .*443|allow |deny ' || true
printf '\nFPM configuration indicators\n'
grep -RHnE '^[[:space:]]*(session.upload_progress.enabled|file_uploads|listen[[:space:]]*=|php_admin_value\[session.upload_progress.enabled\])' /etc/php/*/fpm/ || true
