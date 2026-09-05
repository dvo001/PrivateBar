#!/usr/bin/env python3
"""Small OS integration: Wayland DPMS plus physical touchscreen wake, no web worker."""
import datetime
import json
import os
import select
import subprocess
import time
from zoneinfo import ZoneInfo


def resting(state, now):
    if not state.get('enabled') or state.get('maintenance'):
        return False
    start, end = state['off'], state['on']
    clock = now.strftime('%H:%M')
    return start <= clock < end if start < end else clock >= start or clock < end


def main():
    device = os.environ['PRIVATEBAR_TOUCH_DEVICE']
    output = os.environ.get('PRIVATEBAR_MONITOR_OUTPUT', 'HDMI-A-1')
    fd = os.open(device, os.O_RDONLY | os.O_NONBLOCK)
    last_touch = float('-inf')
    refresh, active, state = 0.0, None, {'enabled': False}
    while True:
        now = time.monotonic()
        if now >= refresh:
            try:
                result = subprocess.run(['/usr/bin/php', '/srv/privatebar/current/artisan', 'privatebar:monitor-state'], capture_output=True, text=True, timeout=10, check=True)
                state = json.loads(result.stdout)
            except (subprocess.SubprocessError, ValueError):
                state = {'enabled': False}  # Background failure leaves the screen usable.
            refresh = now + 30
        readable, _, _ = select.select([fd], [], [], 0.25)
        if readable:
            os.read(fd, 4096)
            last_touch = time.monotonic()
        desired = not resting(state, datetime.datetime.now(ZoneInfo('Europe/Zurich'))) or time.monotonic() - last_touch < 29 * 60
        if desired != active:
            result = subprocess.run(['/usr/bin/wlr-randr', '--output', output, '--on' if desired else '--off'], capture_output=True, timeout=5)
            if result.returncode == 0:
                active = desired


if __name__ == '__main__':
    main()
