set -euo pipefail
python3 - <<'PY'
import json
from pathlib import Path
p=Path('/var/lib/doujin-shop-browser/dom-suruga.json')
data=json.loads(p.read_text(encoding='utf-8'))
print(json.dumps({key:data.get(key) for key in ('url','captured_at','prices','stock','purchase','structured','challenge')}, ensure_ascii=False, indent=2))
PY
