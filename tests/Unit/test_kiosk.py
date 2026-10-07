import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / 'deploy/pi/kiosk.sh'


class KioskStartTest(unittest.TestCase):
    def launch(self, ready):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'curl').write_text('#!/bin/sh\nprintf "%s" "${TEST_HTTP_STATUS}"\n')
            (root / 'sleep').write_text('#!/bin/sh\nexit 0\n')
            (root / 'chromium').write_text('#!/bin/sh\nprintf "%s\\n" "$@" > "$TEST_CHROMIUM_ARGS"\n')
            for name in ['curl', 'sleep', 'chromium']:
                (root / name).chmod(0o755)
            output = root / 'args'
            env = {**os.environ, 'PATH': str(root) + ':' + os.environ['PATH'], 'TEST_HTTP_STATUS': '200' if ready else '503', 'TEST_CHROMIUM_ARGS': str(output)}
            result = subprocess.run(['sh', str(SCRIPT)], env=env, capture_output=True, text=True, timeout=10)
            return result, output.read_text().splitlines() if output.exists() else []

    def test_ready_application_starts_wayland_kiosk(self):
        result, args = self.launch(True)
        self.assertEqual(result.returncode, 0)
        for flag in ['--ozone-platform=wayland', '--enable-wayland-ime', '--wayland-text-input-version=3', '--kiosk', '--incognito', 'https://privatebar.local']:
            self.assertIn(flag, args)

    def test_unavailable_application_does_not_start_blank_browser(self):
        result, args = self.launch(False)
        self.assertEqual(result.returncode, 1)
        self.assertEqual(args, [])
        self.assertIn('nicht erreichbar', result.stderr)


if __name__ == '__main__':
    unittest.main()
