set -euo pipefail
if test -f /etc/apparmor.d/doujin-native-chrome; then
    apparmor_parser -R /etc/apparmor.d/doujin-native-chrome
    rm /etc/apparmor.d/doujin-native-chrome
fi
apparmor_parser -r /etc/apparmor.d/chrome
systemctl restart doujin-shop-browser
