set -euo pipefail
stage="/home/${SUDO_USER:?}/shop-browser-stage"
install -m 644 "$stage/server.py" "$stage/smoke.py" /opt/doujin-shop-browser/
systemctl restart doujin-shop-browser
sleep 2
systemctl status doujin-shop-browser --no-pager | head -16
/opt/doujin-shop-browser/venv/bin/python /opt/doujin-shop-browser/smoke.py
journalctl -u doujin-shop-browser -n 10 --no-pager
