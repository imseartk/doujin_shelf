set -euo pipefail
install -m 644 "/home/${SUDO_USER:?}/shop-browser-stage/shop-browser.js" /var/www/html/doujin/public/assets/shop-browser.js
