import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import json

spec = importlib.util.spec_from_file_location('smb_mount', Path(__file__).resolve().parents[2] / 'deploy/pi/smb-mount.py')
helper = importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)


class SmbMountTest(unittest.TestCase):
    def run_mount(self, requested=False, mounted=False, server='172.16.0.200', fail=False):
        config = {'requested': requested, 'server': server, 'share': 'pi-pics', 'username': 'test', 'password': 'secret'}
        with tempfile.TemporaryDirectory() as directory:
            credentials = Path(directory) / 'credentials'
            def create_credentials(**kwargs):
                import os
                return os.open(credentials, os.O_WRONLY | os.O_CREAT, 0o600), str(credentials)
            with patch.object(helper, 'artisan', side_effect=lambda *args: json.dumps(config) if args[0] == 'privatebar:smb-config' else '') as artisan, \
                 patch.object(helper.os.path, 'ismount', return_value=mounted), \
                 patch.object(helper.os, 'makedirs'), \
                 patch.object(helper.tempfile, 'mkstemp', side_effect=create_credentials), \
                 patch.object(helper.subprocess, 'run') as run:
                if fail:
                    run.side_effect = subprocess.CalledProcessError(32, 'mount')
                helper.main()
                self.assertFalse(credentials.exists(), 'Credentials must always be removed')
                return run.call_args_list, artisan.call_args_list

    def test_missing_mount_after_boot_is_restored(self):
        calls, results = self.run_mount()
        self.assertEqual(len(calls), 1)
        self.assertEqual(calls[0].args[0][:5], ['/usr/bin/mount', '-t', 'cifs', '//172.16.0.200/pi-pics', helper.MOUNT])
        self.assertTrue(calls[0].args[0][-1].startswith('ro,nosuid,nodev,noexec,'))
        self.assertEqual(results[-1].args, ('privatebar:smb-result', 'ok'))

    def test_existing_mount_is_left_untouched(self):
        calls, _ = self.run_mount(mounted=True)
        self.assertEqual(calls, [])

    def test_unconfigured_source_is_left_alone(self):
        calls, _ = self.run_mount(server='')
        self.assertEqual(calls, [])

    def test_explicit_request_remounts_existing_source(self):
        calls, results = self.run_mount(requested=True, mounted=True)
        self.assertEqual(calls[0].args[0], ['/usr/bin/umount', helper.MOUNT])
        self.assertEqual(calls[1].args[0][0], '/usr/bin/mount')
        self.assertEqual(results[-1].args, ('privatebar:smb-result', 'ok'))

    def test_failed_automatic_mount_is_retried_next_run(self):
        calls, results = self.run_mount(fail=True)
        self.assertEqual(results[-1].args, ('privatebar:smb-result', 'error'))
        calls, results = self.run_mount()
        self.assertEqual(results[-1].args, ('privatebar:smb-result', 'ok'))


if __name__ == '__main__':
    unittest.main()
