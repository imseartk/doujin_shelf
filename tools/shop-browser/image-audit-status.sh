#!/bin/bash
ps -eo pid,etime,pcpu,pmem,stat,comm | grep -E 'python3|sshd'
ls -lh /var/www/html/doujin/writable/circlems/catalogs/event_230/webcatalog_image*.db
