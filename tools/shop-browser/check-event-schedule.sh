set -eu
python3 - <<'PY'
import pathlib,subprocess,datetime
p=pathlib.Path('/var/www/html/doujin/app/Views/layouts/app.php')
b=p.read_bytes(); b.decode('utf-8',errors='strict')
line=b'                <a href="/c108/map">C108</a>\n'
if b.count(line)==1:
    backup=pathlib.Path('/var/backups/c109-header-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S')+'.php')
    backup.write_bytes(b);backup.chmod(0o600)
    p.write_bytes(b.replace(line,b''))
subprocess.run(['php','-l',str(p)],check=True)
for folder in ['/etc/cron.d','/var/spool/cron/crontabs']:
    for f in pathlib.Path(folder).glob('*'):
        if not f.is_file():continue
        for line in f.read_text(errors='replace').splitlines():
            if 'circlems:' in line or 'c108' in line.lower() or 'c109' in line.lower():
                print(str(f)+': '+line)
PY
