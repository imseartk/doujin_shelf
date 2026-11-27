#!/bin/bash
set -euo pipefail
umask 077
stage="/home/${SUDO_USER:?}/shop-browser-stage"
app=/var/www/html/doujin
dest=/opt/doujin-ticket-browser
backup=$(mktemp -d /var/backups/ticket-browser-XXXXXXXX)
cp -a "$app/app/Config/Routes.php" "$backup/Routes.php"
printf 'Backup: %s\n' "$backup"
php -l "$stage/TicketBrowser.php"
php -l "$stage/ticket-index.php"
id doujin-ticket >/dev/null 2>&1 || useradd --system --home /var/lib/doujin-ticket-browser --shell /usr/sbin/nologin doujin-ticket
install -d -m 755 "$dest"
install -m 644 /opt/doujin-shop-browser/server.py "$dest/server.py"
install -m 644 /opt/doujin-shop-browser/native_host.py "$dest/native_host.py"
install -m 644 /opt/doujin-shop-browser/native_server.py "$dest/native_server.py"
install -m 644 "$stage/ticket_server.py" "$dest/ticket_server.py"
python3 - <<'PY'
from pathlib import Path
p = Path('/opt/doujin-ticket-browser/native_server.py')
s = p.read_text(encoding='utf-8')
assert "'--app=' + url" in s
s = s.replace('/run/doujin-shop-browser', '/run/doujin-ticket-browser')
s = s.replace("'--app=' + url", "'--incognito', '--disable-extensions', url")
p.write_text(s, encoding='utf-8')
p = Path('/etc/systemd/system/doujin-shop-browser.service')
s = p.read_text(encoding='utf-8')
s = s.replace('doujin-shop-browser', 'doujin-ticket-browser').replace('User=doujin-browser', 'User=doujin-ticket')
s = s.replace('/opt/doujin-ticket-browser/venv/bin/python', '/opt/doujin-shop-browser/venv/bin/python')
s = s.replace('/opt/doujin-ticket-browser/server.py', '/opt/doujin-ticket-browser/ticket_server.py')
s = s.replace('/opt/doujin-ticket-browser/resolv.conf', '/opt/doujin-shop-browser/resolv.conf')
s = s.replace('Private shop verification browser (read-only prototype)', 'Private manual TicketPay browser')
s = s.replace('[Service]', '[Service]\nEnvironment=SHOP_BROWSER_STATE=/var/lib/doujin-ticket-browser\nEnvironment=SHOP_BROWSER_SOCKET=/run/doujin-ticket-browser/agent.sock')
Path('/etc/systemd/system/doujin-ticket-browser.service').write_text(s, encoding='utf-8')
PY
chmod 644 /etc/systemd/system/doujin-ticket-browser.service
install -m 644 "$stage/TicketBrowser.php" "$app/app/Controllers/TicketBrowser.php"
install -d -m 755 "$app/app/Views/ticket_browser"
install -m 644 "$stage/ticket-index.php" "$app/app/Views/ticket_browser/index.php"
install -m 644 "$stage/ticket-browser.js" "$app/public/assets/ticket-browser.js"
python3 - <<'PY'
from pathlib import Path
p=Path('/var/www/html/doujin/app/Config/Routes.php')
b=p.read_bytes()
b.decode('utf-8-sig', errors='strict')
if b'tools/ticket-browser' not in b:
    nl=b'\r\n' if b'\r\n' in b else b'\n'
    routes=[b"$routes->get('tools/ticket-browser', 'TicketBrowser::index', ['filter' => 'adminauth']);",b"$routes->get('tools/ticket-browser/frame', 'TicketBrowser::frame', ['filter' => 'adminauth']);",b"$routes->post('tools/ticket-browser/command', 'TicketBrowser::command', ['filter' => ['adminauth', 'csrf']]);"]
    marker=b"$routes->get('tools/shop-browser', 'ShopBrowser::index');"
    assert b.count(marker)==1
    p.write_bytes(b.replace(marker,marker+nl+nl.join(routes),1))
PY
php -l "$app/app/Config/Routes.php"
systemctl daemon-reload
systemctl enable --now doujin-ticket-browser
systemctl is-active doujin-ticket-browser doujin-shop-browser
