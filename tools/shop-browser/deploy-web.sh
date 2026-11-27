set -euo pipefail
project=/var/www/html/doujin
stage="/home/${SUDO_USER:?}/shop-browser-stage"
cd "$project"
git apply --check --unidiff-zero --ignore-space-change "$stage/web-entry.patch"
for file in ShopBrowser.php ShopProductReader.php browser.php; do php -l "$stage/$file"; done
backup="/var/backups/doujin-shop-browser-$(date +%Y%m%d-%H%M%S)"
install -d -m 700 "$backup"
cp --preserve=mode,timestamps app/Config/Routes.php "$backup/Routes.php"
cp --preserve=mode,timestamps app/Views/sources/index.php "$backup/sources-index.php"
for file in app/Controllers/ShopBrowser.php app/Libraries/ShopProductReader.php app/Views/sources/browser.php public/assets/shop-browser.js; do
    if [ -f "$file" ]; then cp --parents "$file" "$backup/"; fi
done
install -m 644 "$stage/ShopBrowser.php" app/Controllers/ShopBrowser.php
install -m 644 "$stage/ShopProductReader.php" app/Libraries/ShopProductReader.php
install -m 644 "$stage/browser.php" app/Views/sources/browser.php
install -m 644 "$stage/shop-browser.js" public/assets/shop-browser.js
git apply --unidiff-zero --ignore-space-change "$stage/web-entry.patch"
php -l app/Config/Routes.php
php -l app/Views/sources/index.php
echo "Web prototype deployed. Backup: $backup"
