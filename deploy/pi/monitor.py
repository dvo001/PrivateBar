#!/usr/bin/env python3
"""Keep Wayland output powered for the browser OFF clock."""
import os
import subprocess
import time


def resting(state, now):
    if not state.get('enabled') or state.get('maintenance'):
        return False
    start, end = state['off'], state['on']
    clock = now.strftime('%H:%M')
    return start <= clock < end if start < end else clock >= start or clock < end


def main():
    # Die Ruheanzeige läuft im Kioskbrowser. Auch bei alten DPMS-Zuständen
    # muss der Monitor eingeschaltet bleiben, damit die Uhr sichtbar ist.
    output = os.environ.get('PRIVATEBAR_MONITOR_OUTPUT', 'HDMI-A-1')
    while True:
        try:
            subprocess.run(['/usr/bin/wlr-randr', '--output', output, '--on'], capture_output=True, timeout=5)
        except (OSError, subprocess.SubprocessError):
            pass  # Beim nächsten Lauf erneut versuchen.
        time.sleep(30)


if __name__ == '__main__':
    main()
