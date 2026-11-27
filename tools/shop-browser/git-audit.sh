set -eu
cd /var/www/html/doujin
git -c safe.directory=/var/www/html/doujin status --short --untracked-files=normal
git -c safe.directory=/var/www/html/doujin log -3 --format='%h %ad %s' --date=short
git -c safe.directory=/var/www/html/doujin branch --show-current
