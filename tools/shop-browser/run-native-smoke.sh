set -euo pipefail
/opt/doujin-shop-browser/venv/bin/python "/home/${SUDO_USER:?}/shop-browser-stage/smoke-native.py"
systemctl show doujin-shop-browser -p ActiveState -p NRestarts -p MemoryCurrent
