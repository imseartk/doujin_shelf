set -euo pipefail
systemctl show doujin-shop-browser -p ActiveState -p SubState -p NRestarts -p MemoryCurrent -p MemoryPeak -p MemoryMax -p Result
journalctl -u doujin-shop-browser --since '24 hours ago' --no-pager -o cat | grep -E 'Memory|oom|Killed|Traceback|Error|Failed|sandbox|BPF' | tail -30 || true
/opt/doujin-shop-browser/venv/bin/python -c 'import importlib.metadata; print("Playwright", importlib.metadata.version("playwright"))'
free -m
