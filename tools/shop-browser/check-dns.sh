set -euo pipefail
python3 - <<'PY'
import json, socket, urllib.request
host = 'brunhild.challenges.cloudflare.com'
try:
    addresses = socket.getaddrinfo(host,443, type=socket.SOCK_STREAM)
    print('system DNS:', addresses)
    for family, kind, proto, _, address in addresses:
        with socket.socket(family,kind,proto) as connection:
            connection.settimeout(3)
            try:
                connection.connect(address)
                print('TCP 443:', 'connected')
            except OSError as error:
                print('TCP 443:', str(error))
except OSError as error:
    print('system DNS:', str(error))
for provider, url in [('Google', 'https://dns.google/resolve'), ('Cloudflare', 'https://cloudflare-dns.com/dns-query')]:
    request = urllib.request.Request(url + '?name=' + host + '&type=A', headers={'Accept':'application/dns-json'})
    try:
        with urllib.request.urlopen(request,timeout=10) as response:
            data=json.load(response)
        print(provider, 'status:', data.get('Status'), 'answers:', data.get('Answer',[]))
    except Exception as error:
        print(provider, type(error).__name__)
PY
