#!/bin/bash
set -uo pipefail
printf '\n=== Resources ===\n'
uptime
free -m
df -h / /var
printf '\n=== Failed units ===\n'
systemctl --failed --no-pager
printf '\n=== Services ===\n'
systemctl list-units --type=service --all --no-pager --plain
printf '\n=== Timers ===\n'
systemctl list-timers --all --no-pager
printf '\n=== Listeners ===\n'
ss -lntup
printf '\n=== Web configuration ===\n'
nginx -t
php-fpm8.3 -t
nginx -T 2>/dev/null | grep -E 'server_name |proxy_pass |fastcgi_pass ' | sed -E 's#(https?://)[^/@ ]+:[^/@ ]+@#\1[redacted]@#g'
printf '\n=== Website checks ===\n'
for host in doujin.artick.tw doujin-dev.artick.tw artick.tw run.artick.tw; do
  curl --max-time 20 -sS -L -o /dev/null -w "$host HTTP %{http_code} total=%{time_total}s redirects=%{num_redirects}\n" "https://$host/"
done
printf '\n=== Database ping ===\n'
if command -v mysqladmin >/dev/null; then mysqladmin --connect-timeout=5 ping; fi
printf '\n=== Critical services recent error counts ===\n'
for unit in nginx php8.3-fpm mysql mariadb influxdb grafana-server doujin-shop-browser cron; do
  printf '%s: ' "$unit"
  journalctl -u "$unit" --since '30 minutes ago' -p err --no-pager -o cat | wc -l
done
printf '\n=== Reboot flag ===\n'
if [ -f /var/run/reboot-required ]; then cat /var/run/reboot-required; else echo none; fi
printf '\n=== Package audit ===\n'
dpkg --audit
