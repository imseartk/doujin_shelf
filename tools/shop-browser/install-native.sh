set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
stage="/home/${SUDO_USER:?}/shop-browser-stage"
curl --fail --location --proto '=https' --tlsv1.2 https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -o "$stage/google-chrome-stable.deb"
apt-get install -y --no-install-recommends "$stage/google-chrome-stable.deb" xdotool scrot
google-chrome-stable --version
install -m 644 "$stage/native_server.py" /opt/doujin-shop-browser/native_server.py
install -m 755 "$stage/native_host.py" /opt/doujin-shop-browser/native_host.py
# Ubuntu already provides the Chrome user-namespace policy; do not attach twice.
test -f /etc/apparmor.d/chrome
apparmor_parser -r /etc/apparmor.d/chrome
install -d -m 755 /etc/systemd/system/doujin-shop-browser.service.d
install -m 644 "$stage/native.conf" /etc/systemd/system/doujin-shop-browser.service.d/native.conf
install -m 644 "$stage/shop-browser.js" /var/www/html/doujin/public/assets/shop-browser.js
systemctl daemon-reload
systemctl restart doujin-shop-browser
systemctl is-active doujin-shop-browser
