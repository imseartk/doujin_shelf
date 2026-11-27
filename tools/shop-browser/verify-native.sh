set -euo pipefail
google-chrome-stable --version
systemctl show doujin-shop-browser -p ActiveState -p NRestarts -p MemoryCurrent -p IPAddressDeny
python3 - <<'PY'
from pathlib import Path
import pwd
uid=pwd.getpwnam('doujin-browser').pw_uid
processes=[]
for p in Path('/proc').iterdir():
    if not p.name.isdigit(): continue
    try:
        if p.stat().st_uid != uid: continue
        args=(p/'cmdline').read_bytes().split(b'\0')
        if args and (p/'exe').resolve() == Path('/opt/google/chrome/chrome'): processes.append(args)
    except (FileNotFoundError, PermissionError): pass
print('Chrome processes:',len(processes))
assert processes, 'No running Chrome process found'
for flag in (b'--remote-debugging',b'--enable-automation',b'--no-sandbox'):
    print(flag.decode(), 'present:',any(any(arg.startswith(flag) for arg in args) for args in processes))
PY
ss -ltnp | grep -E 'chrome|xdotool|native_server|Xvfb' || true
stat -c '%a %U %G %n' /run/doujin-shop-browser/agent.sock
