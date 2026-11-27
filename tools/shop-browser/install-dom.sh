set -euo pipefail
stage="/home/${SUDO_USER:?}/shop-browser-stage"
root=/opt/doujin-shop-browser
backup="/var/backups/doujin-dom-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
cp "$root/native_server.py" "$backup/"
cp /var/www/html/doujin/app/Controllers/ShopBrowser.php "$backup/"
cp /var/www/html/doujin/public/assets/shop-browser.js "$backup/"
install -d -m 755 "$root/extension"
install -d -m 700 "$root/private"
install -m 644 "$stage/manifest.json" "$stage/background.js" "$stage/capture.js" "$root/extension/"
install -m 755 "$stage/native_host.py" "$root/native_host.py"
install -m 644 "$stage/native_server.py" "$root/native_server.py"
install -m 644 "$stage/register-extension.py" "$root/register-extension.py"
build=$(mktemp -d /var/lib/doujin-shop-browser/dom-build.XXXXXX)
install -d -m 700 -o doujin-browser -g www-data "$build" "$build/extension"
cp "$root/extension/"* "$build/extension/"
chown -R doujin-browser:www-data "$build"
if test -f "$root/private/dom-extension.pem"; then
    install -m 600 -o doujin-browser -g www-data "$root/private/dom-extension.pem" "$build/key.pem"
    keyarg="--pack-extension-key=$build/key.pem"
else
    keyarg=""
fi
runuser -u doujin-browser -- env HOME=/var/lib/doujin-shop-browser xvfb-run -a google-chrome-stable --no-first-run --no-message-box --pack-extension="$build/extension" ${keyarg:+"$keyarg"}
if ! test -f "$root/private/dom-extension.pem"; then
    install -m 600 "$build/extension.pem" "$root/private/dom-extension.pem"
fi
install -m 644 "$build/extension.crx" "$root/extension.crx"
python3 "$root/register-extension.py"
php -l "$stage/ShopBrowser.php"
install -m 644 "$stage/ShopBrowser.php" /var/www/html/doujin/app/Controllers/ShopBrowser.php
install -m 644 "$stage/shop-browser.js" /var/www/html/doujin/public/assets/shop-browser.js
systemctl restart doujin-shop-browser
systemctl is-active doujin-shop-browser
printf 'Backup: %s\n' "$backup"
