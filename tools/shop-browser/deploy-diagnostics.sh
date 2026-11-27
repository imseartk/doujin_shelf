set -euo pipefail
stage="/home/${SUDO_USER:?}/shop-browser-stage"
project=/var/www/html/doujin
php -l "$stage/ShopBrowser.php"
/opt/doujin-shop-browser/venv/bin/python -c 'import ast,sys; ast.parse(open(sys.argv[1], encoding="utf-8").read())' "$stage/server.py"
backup="/var/backups/doujin-shop-browser-diagnostics-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
cp /opt/doujin-shop-browser/server.py "$backup/server.py"
cp "$project/app/Controllers/ShopBrowser.php" "$backup/ShopBrowser.php"
cp "$project/public/assets/shop-browser.js" "$backup/shop-browser.js"
install -m 644 "$stage/server.py" /opt/doujin-shop-browser/server.py
install -m 644 "$stage/ShopBrowser.php" "$project/app/Controllers/ShopBrowser.php"
install -m 644 "$stage/shop-browser.js" "$project/public/assets/shop-browser.js"
systemctl restart doujin-shop-browser
systemctl is-active doujin-shop-browser
printf 'Backup: %s\n' "$backup"
