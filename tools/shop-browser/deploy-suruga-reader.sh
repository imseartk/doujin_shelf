set -euo pipefail
stage="/home/${SUDO_USER:?}/shop-browser-stage"
project=/var/www/html/doujin
php -l "$stage/SurugaSnapshotReader.php"
php -l "$stage/ShopBrowser.php"
install -m 644 "$stage/SurugaSnapshotReader.php" "$project/app/Libraries/SurugaSnapshotReader.php"
install -m 644 "$stage/ShopBrowser.php" "$project/app/Controllers/ShopBrowser.php"
install -m 644 "$stage/shop-browser.js" "$project/public/assets/shop-browser.js"
php "$stage/verify-suruga-cases.php"
