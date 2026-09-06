#!/usr/bin/env python3
"""Root-owned one-shot helper. Never print or log credentials."""
import json
import os
import re
import subprocess
import tempfile

APP = '/srv/privatebar/current'
MOUNT = '/mnt/privatebar-photos'


def artisan(*arguments):
    result = subprocess.run(['/usr/sbin/runuser', '-u', 'privatebar', '--', '/usr/bin/php8.3', APP + '/artisan', *arguments], cwd=APP, capture_output=True, text=True, timeout=30, check=True)
    return result.stdout


def main():
    config = json.loads(artisan('privatebar:smb-config'))
    if not config.get('requested'):
        return
    server, share = config.get('server') or '', config.get('share') or ''
    subpath = config.get('subpath') or ''
    if not re.fullmatch(r'[a-zA-Z0-9.-]{1,253}', server) or not re.fullmatch(r'[\w .-]{1,100}', share):
        artisan('privatebar:smb-result', 'error')
        return
    if '..' in subpath or any(c in subpath for c in ',\n\r\x00'):
        artisan('privatebar:smb-result', 'error')
        return
    user, password = config.get('username') or '', config.get('password') or ''
    if any(c in user + password for c in '\n\r\x00'):
        artisan('privatebar:smb-result', 'error')
        return
    os.makedirs(MOUNT, mode=0o755, exist_ok=True)
    os.makedirs('/run/privatebar', mode=0o700, exist_ok=True)
    fd, credentials = tempfile.mkstemp(dir='/run/privatebar', prefix='smb-')
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write('username=' + user + '\npassword=' + password + '\n')
        if os.path.ismount(MOUNT):
            subprocess.run(['/usr/bin/umount', MOUNT], capture_output=True, timeout=15, check=True)
        options = 'ro,nosuid,nodev,noexec,vers=3.0,credentials=' + credentials
        if subpath:
            options += ',prefixpath=' + subpath.strip('/')
        subprocess.run(['/usr/bin/mount', '-t', 'cifs', '//' + server + '/' + share, MOUNT, '-o', options], capture_output=True, timeout=20, check=True)
        artisan('privatebar:smb-result', 'ok')
    except (subprocess.SubprocessError, OSError):
        artisan('privatebar:smb-result', 'error')
    finally:
        os.unlink(credentials)


if __name__ == '__main__':
    main()
