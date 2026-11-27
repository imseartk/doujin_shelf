"""Read-only image catalog metadata audit; never export image contents."""
import collections
import hashlib
import json
import pathlib
import sqlite3
import sys

for arg in sys.argv[1:]:
    path = pathlib.Path(arg).resolve()
    if not path.is_file():
        print(json.dumps({'file': str(path), 'missing': True}))
        continue
    db = sqlite3.connect(path.as_uri() + '?mode=ro', uri=True)
    result = {'file': path.name, 'bytes': path.stat().st_size,
              'check': db.execute('PRAGMA quick_check').fetchall(), 'tables': {}}
    for (table,) in db.execute("SELECT name FROM sqlite_master WHERE type='table'"):
        quoted = '"' + table.replace('"', '""') + '"'
        cols = db.execute('PRAGMA table_info(' + quoted + ')').fetchall()
        names = [col[1] for col in cols]
        info = {'columns': cols, 'rows': db.execute('SELECT count(*) FROM ' + quoted).fetchone()[0]}
        result['tables'][table] = info
        if not {'comiketNo', 'width', 'height', 'type', 'size', 'md5'} <= set(names):
            continue
        info['groups'] = db.execute('SELECT comiketNo,width,height,type,count(*) FROM ' + quoted + ' GROUP BY 1,2,3,4').fetchall()
        if 'WCId' in names:
            info['ids'] = db.execute('SELECT min(id),max(id),count(distinct WCId),sum(WCId=0),min(WCId),max(WCId) FROM ' + quoted).fetchone()
        blob = 'cutImage' if 'cutImage' in names else 'image'
        hashes = collections.Counter()
        signatures = collections.Counter()
        bad_size = bad_md5 = empty = 0
        for data, size, md5 in db.execute('SELECT ' + blob + ',size,md5 FROM ' + quoted):
            if not data:
                empty += 1
                continue
            digest = hashlib.md5(data).hexdigest()
            hashes[digest] += 1
            signatures[data[:8].hex()] += 1
            bad_size += len(data) != size
            expected = md5.hex() if isinstance(md5, bytes) and len(md5) == 16 else str(md5)
            bad_md5 += digest != expected.lower()
        info.update(unique_images=len(hashes), empty=empty, size_mismatch=bad_size,
                    md5_mismatch=bad_md5, signatures=dict(signatures))
    print(json.dumps(result, ensure_ascii=True))
    db.close()
