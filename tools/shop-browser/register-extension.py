"""Register the generated signed Linux CRX and its narrowly scoped native host."""
import hashlib
import json
import subprocess
from pathlib import Path

root = Path('/opt/doujin-shop-browser')
key = root / 'private/dom-extension.pem'
public = subprocess.check_output(['openssl', 'rsa', '-in', str(key), '-pubout', '-outform', 'DER'], stderr=subprocess.DEVNULL)
extension_id = ''.join(chr(ord('a') + int(c, 16)) for c in hashlib.sha256(public).hexdigest()[:32])
version = json.loads((root / 'extension/manifest.json').read_text())['version']
external = Path('/usr/share/google-chrome/extensions')
external.mkdir(parents=True, exist_ok=True)
(external / (extension_id + '.json')).write_text(json.dumps({
    'external_crx': str(root / 'extension.crx'), 'external_version': version,
}), encoding='utf-8')
hosts = Path('/etc/opt/chrome/native-messaging-hosts')
hosts.mkdir(parents=True, exist_ok=True)
(hosts / 'tw.artick.product_dom.json').write_text(json.dumps({
    'name':'tw.artick.product_dom', 'description':'Product DOM snapshot receiver',
    'path':str(root / 'native_host.py'), 'type':'stdio',
    'allowed_origins':['chrome-extension://' + extension_id + '/'],
}), encoding='utf-8')
print('Extension ID:', extension_id)
