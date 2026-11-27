#!/opt/doujin-shop-browser/venv/bin/python
"""Bounded read-only DOM snapshots from the installed extension. No networking."""
import json
import os
import struct
import sys
import tempfile
import time
from pathlib import Path
from urllib.parse import urlsplit, parse_qs, urlencode

ROOT = Path('/var/lib/doujin-shop-browser')


def identity(url):
    p = urlsplit(url)
    if p.scheme != 'https' or p.username or p.password or p.port:
        raise ValueError('Unsupported URL')
    query = parse_qs(p.query)
    if p.hostname in ('www.suruga-ya.jp', 'suruga-ya.jp') and p.path.startswith('/product/detail/') and p.path.removeprefix('/product/detail/').isalnum():
        site, keys = 'suruga', ['tenpo_cd']
    elif p.hostname in ('www.melonbooks.co.jp', 'melonbooks.co.jp') and p.path == '/detail/detail.php' and query.get('product_id', [''])[0].isdigit():
        site, keys = 'melon', ['product_id']
    elif p.hostname == 'order.mandarake.co.jp' and p.path == '/order/detailPage/item' and query.get('itemCode', [''])[0].isdigit():
        site, keys = 'mandarake', ['itemCode']
    else:
        raise ValueError('Unsupported product')
    safe_query = urlencode({key: query[key][0] for key in keys if key in query})
    return site, 'https://' + p.hostname + p.path + ('?' + safe_query if safe_query else '')


def main():
    header = sys.stdin.buffer.read(4)
    if len(header) != 4: return
    size = struct.unpack('<I', header)[0]
    if not 0 < size <= 128000: return
    payload = sys.stdin.buffer.read(size)
    if len(payload) != size: return
    data = json.loads(payload)
    site, url = identity(data['url'])
    allowed = ('schemaVersion', 'canonical', 'suruga', 'title', 'headings', 'prices', 'stock', 'purchase', 'structured', 'challenge')
    snapshot = {key: data[key] for key in allowed if key in data}
    snapshot.update(url=url, captured_at=time.time())
    fd, temp = tempfile.mkstemp(prefix='dom-', dir=ROOT)
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as output:
            json.dump(snapshot, output, ensure_ascii=False)
        os.replace(temp, ROOT / ('dom-' + site + '.json'))
    finally:
        Path(temp).unlink(missing_ok=True)
    reply = b'{"ok":true}'
    sys.stdout.buffer.write(struct.pack('<I', len(reply)) + reply)
    sys.stdout.buffer.flush()


if __name__ == '__main__':
    try:
        main()
    except Exception:
        # Native messaging stdout is a protocol, never a debug log.
        sys.exit(1)
