"""SSH transport for explicit deployment commands; secrets stay outside the repository."""
import argparse
import base64
import hashlib
import shlex
import socket
import sys
from pathlib import Path

import paramiko

parser = argparse.ArgumentParser()
parser.add_argument('--credentials', required=True)
parser.add_argument('--host-key', required=True)
sub = parser.add_subparsers(dest='operation', required=True)
run = sub.add_parser('run')
run.add_argument('script')
run.add_argument('--sudo', action='store_true')
put = sub.add_parser('put')
put.add_argument('local')
put.add_argument('remote')
get = sub.add_parser('get')
get.add_argument('remote')
get.add_argument('local')
args = parser.parse_args()
config = dict(line.split('=', 1) for line in Path(args.credentials).read_text(encoding='utf-8-sig').splitlines() if '=' in line)
transport = paramiko.Transport(socket.create_connection((config['ip'], int(config.get('port', 22))), timeout=15))
try:
    transport.start_client(timeout=15)
    actual = 'SHA256:' + base64.b64encode(hashlib.sha256(transport.get_remote_server_key().asbytes()).digest()).decode().rstrip('=')
    if actual != args.host_key:
        raise RuntimeError('SSH host fingerprint changed; connection stopped')
    transport.auth_password(config['user'], config['password'])
    if args.operation == 'put':
        with paramiko.SFTPClient.from_transport(transport) as sftp:
            sftp.put(args.local, args.remote)
        print('Uploaded', Path(args.local).name)
    elif args.operation == 'get':
        with paramiko.SFTPClient.from_transport(transport) as sftp:
            sftp.get(args.remote, args.local)
        print('Downloaded', Path(args.local).name)
    else:
        channel = transport.open_session()
        channel.exec_command('sudo -S -p "" /bin/bash -se' if args.sudo else '/bin/bash -se')
        if args.sudo:
            channel.sendall(config['password'] + '\n')
        channel.sendall(Path(args.script).read_bytes())
        channel.shutdown_write()
        import select
        while True:
            if channel.recv_ready():
                sys.stdout.buffer.write(channel.recv(8192)); sys.stdout.buffer.flush()
            if channel.recv_stderr_ready():
                sys.stderr.buffer.write(channel.recv_stderr(8192)); sys.stderr.buffer.flush()
            if channel.exit_status_ready() and not channel.recv_ready() and not channel.recv_stderr_ready():
                break
            select.select([channel], [], [], 0.2)
        sys.exit(channel.recv_exit_status())
finally:
    transport.close()
