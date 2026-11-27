"""Export private sample circle cuts without changing the source database."""
import csv
import hashlib
import pathlib
import sqlite3
import datetime

desktop = pathlib.Path.home() / 'Desktop'
source = desktop / 'webcatalog109Image1.db'
db = sqlite3.connect(source.as_uri() + '?mode=ro', uri=True)
db.execute('PRAGMA query_only=ON')
folder = desktop / ('C109-circle-images-private-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S'))
folder.mkdir(exist_ok=False)
count = 0
with (folder / 'index.csv').open('x', encoding='utf-8-sig', newline='') as index:
    writer = csv.writer(index)
    writer.writerow(['file', 'comiketNo', 'id', 'WCId', 'width', 'height'])
    for event, ident, wcid, width, height, size, md5, data in db.execute(
        'SELECT comiketNo,id,WCId,width,height,size,md5,cutImage FROM ComiketCircleImage ORDER BY id'
    ):
        if not data or not data.startswith(b'\x89PNG\r\n\x1a\n'):
            raise ValueError('Invalid PNG at id ' + str(ident))
        expected = md5.hex() if isinstance(md5, bytes) else str(md5).lower()
        if len(data) != size or hashlib.md5(data).hexdigest() != expected:
            raise ValueError('Checksum mismatch at id ' + str(ident))
        name = 'wcid_' + str(int(wcid)) + '_id_' + str(int(ident)) + '.png'
        with (folder / name).open('xb') as output:
            output.write(data)
        writer.writerow([name, event, ident, wcid, width, height])
        count += 1
db.close()
assert len(list(folder.glob('*.png'))) == count
print(str(folder), flush=True)
print('Verified PNG files: ' + str(count), flush=True)
