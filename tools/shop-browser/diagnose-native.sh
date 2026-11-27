set -euo pipefail
journalctl -k --since '10 minutes ago' --no-pager | grep -E 'apparmor|DENIED|chrome|oom' | tail -25 || true
systemctl status doujin-shop-browser --no-pager | head -20
ls -l /opt/google/chrome/chrome-sandbox
grep -l '/opt/google/chrome/chrome' /etc/apparmor.d/* 2>/dev/null || true
