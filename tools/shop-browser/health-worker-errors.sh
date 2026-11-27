#!/bin/bash
set -uo pipefail
journalctl -u garmin-grafana --since '24 hours ago' --no-pager -o cat | python3 -c '
import sys,re,collections
counts=collections.Counter()
for line in sys.stdin:
    if re.search(r"error|exception|traceback|failed",line,re.I):
        line=re.sub(r"https?://\S+", "[URL]",line)
        line=re.sub(r"(?i)(token|password|authorization|secret)[\s:=]+\S+",r"\1=[REDACTED]",line)
        print(line[:400].strip())
'
printf '\n=== Worker most recent event timestamp ===\n'
journalctl -u garmin-grafana -n 1 --no-pager -o json | python3 -c 'import sys,json,datetime; d=json.load(sys.stdin); print(datetime.datetime.fromtimestamp(int(d["__REALTIME_TIMESTAMP"])/1e6, datetime.timezone.utc).isoformat())'
