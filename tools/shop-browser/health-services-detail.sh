#!/bin/bash
set -uo pipefail
printf '\n=== Redis ===\n'
redis-cli ping
printf '\n=== Grafana ===\n'
curl -fsS --max-time 10 http://127.0.0.1:3000/api/health
printf '\n=== InfluxDB ===\n'
curl -sS --max-time 10 -o /dev/null -w 'InfluxDB ping HTTP %{http_code}\n' http://127.0.0.1:8086/ping
printf '\n=== Browser socket ===\n'
curl --unix-socket /run/doujin-shop-browser/agent.sock --max-time 10 -sS -o /dev/null -w 'HTTP %{http_code} (GET on POST-only route)\n' http://localhost/command
printf '\n=== Firmware refresh failure ===\n'
systemctl show fwupd-refresh -p Result -p ExecMainStatus -p ExecMainExitTimestamp
journalctl -u fwupd-refresh -n 12 --no-pager -o cat
printf '\n=== Reboot request ===\n'
stat -c '%y %n' /var/run/reboot-required
cat /var/run/reboot-required.pkgs
printf '\n=== Firewall ===\n'
ufw status verbose
printf '\n=== Worker/service restarts ===\n'
for unit in garmin-grafana grafana-server influxdb redis-server mariadb doujin-shop-browser php8.3-fpm cron; do
  echo "$unit"
  systemctl show "$unit" -p ActiveState -p NRestarts -p ActiveEnterTimestamp
done
printf '\n=== Garmin recent log severity counts (no health data) ===\n'
journalctl -u garmin-grafana --since '24 hours ago' --no-pager -o cat | awk 'BEGIN{n=0;e=0} {n++; if(tolower($0) ~ /error|exception|traceback|failed/)e++} END{print "lines="n, "error-like lines="e}'
printf '\n=== Certificates ===\n'
for cert in /etc/letsencrypt/live/*/cert.pem; do
  echo "$cert"
  openssl x509 -in "$cert" -noout -enddate
done
