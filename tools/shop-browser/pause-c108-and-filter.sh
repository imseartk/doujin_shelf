set -eu
python3 - <<'PY'
import subprocess,pathlib,datetime,shutil
r=subprocess.run(['crontab','-u','artick','-l'],capture_output=True,text=True,check=True)
lines=r.stdout.splitlines(True);changed=False
for i,line in enumerate(lines):
    if not line.lstrip().startswith('#') and 'circlems:check-c108-updates' in line:
        lines[i]='# Paused pending C109 notifications: '+line;changed=True
if changed:
    p=pathlib.Path('/var/backups/c108-cron-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'))
    p.write_text(r.stdout);p.chmod(0o600)
    subprocess.run(['crontab','-u','artick','-'],input=''.join(lines),text=True,check=True)
root=pathlib.Path('/var/www/html/doujin')
for src,dest in [('Circles-filter.php','app/Controllers/Circles.php'),('circles-filter-view.php','app/Views/circles/index.php')]:
    source=pathlib.Path('/home/artick/shop-browser-stage')/src
    subprocess.run(['php','-l',str(source)],check=True)
    backup=pathlib.Path('/var/backups/'+src+'-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));shutil.copy2(root/dest,backup)
    shutil.copyfile(source,root/dest)
print('C108 notification cron paused; C109 binding filter deployed.')
PY
