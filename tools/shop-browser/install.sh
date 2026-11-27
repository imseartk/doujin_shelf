#!/bin/bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y --no-install-recommends python3-venv xvfb xauth fonts-noto-cjk
id doujin-browser >/dev/null 2>&1 || useradd --system --home-dir /var/lib/doujin-shop-browser --shell /usr/sbin/nologin doujin-browser
install -d -m 755 /opt/doujin-shop-browser
python3 -m venv /opt/doujin-shop-browser/venv
/opt/doujin-shop-browser/venv/bin/pip install -r /opt/doujin-shop-browser/requirements.txt
PLAYWRIGHT_BROWSERS_PATH=/opt/doujin-shop-browser/browsers /opt/doujin-shop-browser/venv/bin/playwright install --with-deps chromium
install -m 644 /opt/doujin-shop-browser/apparmor-profile /etc/apparmor.d/doujin-shop-chromium
apparmor_parser -r /etc/apparmor.d/doujin-shop-chromium
install -m 644 /opt/doujin-shop-browser/doujin-shop-browser.service /etc/systemd/system/doujin-shop-browser.service
systemd-analyze verify /etc/systemd/system/doujin-shop-browser.service
systemctl daemon-reload
systemctl enable --now doujin-shop-browser.service
systemctl is-active doujin-shop-browser.service
